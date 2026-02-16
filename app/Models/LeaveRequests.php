<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequests extends Model
{
    protected $table = 'leave_requests';

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'request_days',
        'reason',
        'status',
        'decided_by',
        'decided_at',
        'decision_note',
        'attachment',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
