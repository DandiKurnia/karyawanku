<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Requests\DecideLeaveRequestsRequest;
use App\Http\Requests\StoreLeaveRequestsRequest;
use App\Models\LeaveEntitlements;
use App\Models\LeaveRequests;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveRequestsController extends Controller
{
    public function index(Request $request)
    {
        $id = $request->input('id');
        $status = $request->input('status');
        $year = $request->input('year');
        $userId = $request->input('user_id');
        $limit = $request->input('limit', 10);

        $user = $request->user();

        if ($id) {
            $leaveRequest = LeaveRequests::with(['user', 'decidedBy'])->find($id);

            if (!$leaveRequest) {
                return ResponseFormatter::error(null, 'Data Not Found', 404);
            }

            if ($user->role !== 'admin' && $leaveRequest->user_id !== $user->id) {
                return ResponseFormatter::error(null, 'Forbidden: You do not have access to this resource', 403);
            }

            return ResponseFormatter::success($leaveRequest, 'Success Get Data');
        }

        $leaveRequests = LeaveRequests::with(['user', 'decidedBy'])->orderByDesc('created_at');

        if ($user->role !== 'admin') {
            $leaveRequests->where('user_id', $user->id);
        } elseif ($userId) {
            $leaveRequests->where('user_id', $userId);
        }

        if ($status) {
            $leaveRequests->where('status', $status);
        }

        if ($year) {
            $leaveRequests->whereYear('start_date', $year);
        }

        $result = $leaveRequests->paginate($limit);

        if ($result->count() === 0) {
            return ResponseFormatter::error(null, 'Data Not Found', 404);
        }

        return ResponseFormatter::success($result, 'Success Get Data');
    }

    public function show(LeaveRequests $leave_request, Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'admin' && $leave_request->user_id !== $user->id) {
            return ResponseFormatter::error(null, 'Forbidden: You do not have access to this resource', 403);
        }

        return ResponseFormatter::success(
            $leave_request->load(['user', 'decidedBy']),
            'Success Get Data'
        );
    }

    public function store(StoreLeaveRequestsRequest $request)
    {
        $user = $request->user();

        if ($user->role !== 'employee') {
            return ResponseFormatter::error(null, 'Forbidden: Only employee can create leave requests', 403);
        }

        try {
            $data = $request->validated();

            $startDate = Carbon::parse($data['start_date'])->startOfDay();
            $endDate = Carbon::parse($data['end_date'])->startOfDay();

            if ($startDate->year !== $endDate->year) {
                return ResponseFormatter::error(null, 'Start date and end date must be within the same year', 400);
            }

            $requestDays = $startDate->diffInDays($endDate) + 1;

            if ($requestDays <= 0) {
                return ResponseFormatter::error(null, 'Invalid date range', 400);
            }

            $hasOverlap = LeaveRequests::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'approved'])
                ->where('start_date', '<=', $endDate)
                ->where('end_date', '>=', $startDate)
                ->exists();

            if ($hasOverlap) {
                return ResponseFormatter::error(
                    null,
                    'Your leave request overlaps with an existing pending or approved leave.',
                    409
                );
            }

            $year = $startDate->year;

            $entitlement = LeaveEntitlements::firstOrCreate(
                ['user_id' => $user->id, 'year' => $year],
                ['quota_days' => 12, 'carried_forward_days' => 0, 'created_by' => null]
            );

            $approvedDays = LeaveRequests::where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereYear('start_date', $year)
                ->sum('request_days');

            $availableDays = ($entitlement->quota_days + $entitlement->carried_forward_days) - $approvedDays;

            if ($requestDays > $availableDays) {
                return ResponseFormatter::error(null, 'Insufficient leave quota', 400);
            }

            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store(
                    'leave-attachments/' . $user->id . '/' . $year,
                    'local'
                );
            }

            $leaveRequest = LeaveRequests::create([
                'user_id' => $user->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'request_days' => $requestDays,
                'reason' => $data['reason'],
                'status' => 'pending',
                'attachment' => $attachmentPath,
            ]);

            return ResponseFormatter::success(
                $leaveRequest->load(['user', 'decidedBy']),
                'Success Create Data',
                201
            );
        } catch (\Exception $error) {
            return ResponseFormatter::error(null, 'Internal Server Error', 500);
        }
    }

    public function cancel(LeaveRequests $leave_request, Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'employee') {
            return ResponseFormatter::error(null, 'Forbidden: Only employee can cancel leave requests', 403);
        }

        if ($leave_request->user_id !== $user->id) {
            return ResponseFormatter::error(null, 'Forbidden: You do not have access to this resource', 403);
        }

        if ($leave_request->status !== 'pending') {
            return ResponseFormatter::error(null, 'Cannot cancel leave request that is not pending', 400);
        }

        $leave_request->update(['status' => 'cancelled']);

        return ResponseFormatter::success(
            $leave_request->load(['user', 'decidedBy']),
            'Success Cancel Data'
        );
    }

    public function decide(DecideLeaveRequestsRequest $request, LeaveRequests $leave_request)
    {
        $admin = $request->user();

        if ($admin->role !== 'admin') {
            return ResponseFormatter::error(null, 'Forbidden: Only admin can decide leave requests', 403);
        }

        $data = $request->validated();

        return DB::transaction(function () use ($leave_request, $admin, $data) {
            $lockedLeaveRequest = LeaveRequests::whereKey($leave_request->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedLeaveRequest) {
                return ResponseFormatter::error(null, 'Data Not Found', 404);
            }

            if ($lockedLeaveRequest->status !== 'pending') {
                return ResponseFormatter::error(null, 'Leave request has already been decided', 409);
            }

            $decision = $data['status'];

            if ($decision === 'approved') {
                $employee = User::whereKey($lockedLeaveRequest->user_id)
                    ->lockForUpdate()
                    ->first();

                if (!$employee) {
                    return ResponseFormatter::error(null, 'User Not Found', 404);
                }

                $year = Carbon::parse($lockedLeaveRequest->start_date)->year;

                $entitlement = LeaveEntitlements::firstOrCreate(
                    ['user_id' => $employee->id, 'year' => $year],
                    ['quota_days' => 12, 'carried_forward_days' => 0, 'created_by' => null]
                );

                $approvedDays = LeaveRequests::where('user_id', $employee->id)
                    ->where('status', 'approved')
                    ->whereYear('start_date', $year)
                    ->sum('request_days');

                $availableDays = ($entitlement->quota_days + $entitlement->carried_forward_days) - $approvedDays;

                if ($lockedLeaveRequest->request_days > $availableDays) {
                    return ResponseFormatter::error(null, 'Insufficient leave quota to approve this request', 409);
                }
            }

            $lockedLeaveRequest->update([
                'status' => $decision,
                'decided_by' => $admin->id,
                'decided_at' => now(),
                'decision_note' => $data['decision_note'] ?? null,
            ]);

            return ResponseFormatter::success(
                $lockedLeaveRequest->load(['user', 'decidedBy']),
                'Success Update Data'
            );
        });
    }
}
