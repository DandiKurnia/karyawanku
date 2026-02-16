<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Requests\DecideLeaveRequestsRequest;
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

        if ($id) {
            $leaveRequest = LeaveRequests::with(['user', 'decidedBy'])->find($id);

            if (!$leaveRequest) {
                return ResponseFormatter::error(null, 'Data Not Found', 404);
            }

            return ResponseFormatter::success($leaveRequest, 'Success Get Data');
        }

        $leaveRequests = LeaveRequests::with(['user', 'decidedBy'])->orderByDesc('created_at');

        if ($userId) {
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

    public function show(LeaveRequests $leave_request)
    {
        return ResponseFormatter::success(
            $leave_request->load(['user', 'decidedBy']),
            'Success Get Data'
        );
    }

    public function decide(DecideLeaveRequestsRequest $request, LeaveRequests $leave_request)
    {
        $admin = $request->user();

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
