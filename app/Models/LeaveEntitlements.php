<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveEntitlements extends Model
{
    protected $table = 'leave_entitlements';

    protected $fillable = [
        'user_id',
        'year',
        'quota_days',
        'carried_forward_days',
        'created_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
