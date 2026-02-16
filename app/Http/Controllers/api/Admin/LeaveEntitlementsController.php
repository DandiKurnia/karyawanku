<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\LeaveEntitlements;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreLeaveEntitlementsRequest;
use App\Http\Requests\UpdateLeaveEntitlementsRequest;

class LeaveEntitlementsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $id = $request->id;
        $name = $request->name;
        $year = $request->year;
        $created_by = $request->created_by;
        $limit = $request->input('limit', 10);

        if ($id) {
            $leaveEntitlements = LeaveEntitlements::with(['user', 'createdBy'])->find($id);

            if ($leaveEntitlements) {
                return ResponseFormatter::success(
                    $leaveEntitlements,
                    'Success Get Data'
                );
            } else {
                return ResponseFormatter::error(
                    null,
                    'Data Not Found',
                    404
                );
            }
        }

        $leaveEntitlements = LeaveEntitlements::with(['user', 'createdBy']);

        if ($name) {
            $leaveEntitlements->whereHas('user', function ($query) use ($name) {
                $query->where('name', 'like', '%' . $name . '%');
            });
        }

        if ($created_by === 'me') {
            $leaveEntitlements->where('created_by', $request->user()->id);
        }

        if ($year) {
            $leaveEntitlements->where('year', $year);
        }

        $result = $leaveEntitlements->paginate($limit);

        if ($result->count() > 0) {
            return ResponseFormatter::success(
                $result,
                'Success Get Data'
            );
        } else {
            return ResponseFormatter::error(
                null,
                'Data Not Found',
                404
            );
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLeaveEntitlementsRequest $request)
    {
        try {
            $data = $request->validated();

            $user = User::find($data['user_id']);

            if (!$user) {
                return ResponseFormatter::error(
                    null,
                    'User Not Found',
                    404
                );
            }

            $isEmployee = $user->role === 'employee';

            if (!$isEmployee) {
                return ResponseFormatter::error(
                    null,
                    'User Not Employee',
                    404
                );
            }

            $leaveEntitlements = LeaveEntitlements::where('user_id', $data['user_id'])->where('year', $data['year'])->first();

            if ($leaveEntitlements) {
                return ResponseFormatter::error(
                    null,
                    'Leave Entitlements Already Exists',
                    409
                );
            }

            $leaveEntitlements = LeaveEntitlements::create([
                'user_id' => $data['user_id'],
                'year' => $data['year'],
                'quota_days' => $data['quota_days'],
                'created_by' => Auth::user()->id,
            ]);

            return ResponseFormatter::success(
                $leaveEntitlements->load(['user', 'createdBy']),
                'Success Create Data'
            );
        } catch (\Exception $error) {

            return ResponseFormatter::error(
                null,
                'Internal Server Error',
                500
            );
        }
    }
    /**
     * Display the specified resource.
     */
    public function show(LeaveEntitlements $leave_entitlement)
    {
        try {
            $leaveEntitlements = $leave_entitlement->load(['user', 'createdBy']);

            return ResponseFormatter::success(
                $leaveEntitlements,
                'Success Get Data'
            );
        } catch (\Exception $error) {

            return ResponseFormatter::error(
                null,
                'Internal Server Error',
                500
            );
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLeaveEntitlementsRequest $request, LeaveEntitlements $leave_entitlement)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LeaveEntitlements $leave_entitlement)
    {
        //
    }
}
