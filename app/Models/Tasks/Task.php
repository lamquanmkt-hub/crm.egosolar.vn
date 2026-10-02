<?php

namespace App\Models\Tasks;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $table = 'tasks';

    protected $fillable = [
        'title',
        'description',
        'requester_id',
        'assignee_id',
        'priority',
        'status',
        'progress_percent',
        'due_at',
        'link_url',
        'attachment_path',
        'result_note',
        'result_attachment_path',
        'completed_at',
        'submitted_at',
        'approved_at',
        'approved_by',
        'manager_feedback',
        'revision_reason',
        'revision_requested_by',
        'revision_requested_at',
        'resubmitted_at',
        'task_group_id',
        'company_id',
        'site_id',
        'proposal_id',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'revision_requested_at' => 'datetime',
        'resubmitted_at' => 'datetime',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function workReports()
    {
        return $this->hasMany(TaskWorkReport::class, 'task_id')->latest('id');
    }

    public function site()
    {
        return $this->belongsTo(\App\Models\Projects\Site::class, 'site_id');
    }
}
