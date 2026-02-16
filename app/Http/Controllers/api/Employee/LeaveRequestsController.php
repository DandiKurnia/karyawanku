<?php

namespace App\Http\Controllers\Api\Employee;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaveRequestsRequest;
use App\Models\LeaveEntitlements;
use App\Models\LeaveRequests;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LeaveRequestsController extends Controller
{
    public function index(Request $request)
    {
        $id = $request->input('id');
        $status = $request->input('status');
        $year = $request->input('year');
        $limit = $request->input('limit', 10);

        $user = $request->user();

        if ($id) {
            $leaveRequest = LeaveRequests::with(['user', 'decidedBy'])->find($id);

            if (!$leaveRequest) {
                return ResponseFormatter::error(null, 'Data Not Found', 404);
            }

            if ($leaveRequest->user_id !== $user->id) {
                return ResponseFormatter::error(null, 'Forbidden: You do not have access to this resource', 403);
            }

            return ResponseFormatter::success($leaveRequest, 'Success Get Data');
        }

        $leaveRequests = LeaveRequests::with(['user', 'decidedBy'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');

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

        if ($leave_request->user_id !== $user->id) {
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

    public function myQuota(Request $request)
    {
        $user = $request->user();

        $year = $request->input('year', Carbon::now()->year);

        $entitlement = LeaveEntitlements::where('user_id', $user->id)
            ->where('year', $year)
            ->first();

        if (!$entitlement) {
            return ResponseFormatter::success([
                'year' => (int) $year,
                'quota_days' => 12,
                'approved_days' => 0,
                'pending_days' => 0,
                'remaining_days' => 12,
            ], 'Success Get Data');
        }

        $approvedDays = LeaveRequests::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->sum('request_days');

        $pendingDays = LeaveRequests::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereYear('start_date', $year)
            ->sum('request_days');

        $totalQuota = $entitlement->quota_days;
        $remainingDays = $totalQuota - $approvedDays;

        return ResponseFormatter::success([
            'year' => (int) $year,
            'quota_days' => $totalQuota,
            'approved_days' => (int) $approvedDays,
            'pending_days' => (int) $pendingDays,
            'remaining_days' => $remainingDays,
        ], 'Success Get Data');
    }
}
