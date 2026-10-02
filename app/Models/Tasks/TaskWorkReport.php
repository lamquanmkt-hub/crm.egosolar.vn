<?php

namespace App\Models\Tasks;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TaskWorkReport extends Model
{
    protected $table = 'task_work_reports';

    protected $fillable = [
        'task_id',
        'user_id',
        'report_date',
        'progress_percent',
        'result_note',
        'employee_note',
        'status',
        'submitted_at',
        'approved_at',
        'approved_by',
        'manager_feedback',
        'revision_reason',
    ];

    protected $casts = [
        'report_date' => 'date',
        'progress_percent' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
