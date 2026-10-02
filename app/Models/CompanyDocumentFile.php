<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CompanyDocumentFile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'department',
        'folder_id',
        'name',
        'description',
        'tags',
        'approval_status',
        'approval_note',
        'version_no',
        'review_due_at',
        'approved_by',
        'approved_at',
        'original_name',
        'path',
        'mime',
        'size',
        'uploaded_by',
        'updated_by',
    ];

    protected $casts = [
        'tags' => 'array',
        'review_due_at' => 'date',
        'approved_at' => 'datetime',
        'version_no' => 'integer',
        'size' => 'integer',
    ];

    protected $appends = ['display_name', 'extension'];

    public function folder()
    {
        return $this->belongsTo(CompanyDocumentFolder::class, 'folder_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function versions()
    {
        return $this->hasMany(CompanyDocumentVersion::class, 'file_id')->orderByDesc('version_no');
    }

    public function activities()
    {
        return $this->hasMany(CompanyDocumentActivity::class, 'file_id')->latest();
    }

    public function getDisplayNameAttribute(): string
    {
        $original = trim((string) $this->original_name);
        $custom = trim((string) $this->name);

        if ($custom === '') {
            return $original ?: basename((string) $this->path);
        }

        $stem = pathinfo($original, PATHINFO_FILENAME);
        if ($stem !== '' && Str::slug($stem) === $custom) {
            return $original;
        }

        return $custom;
    }

    public function getExtensionAttribute(): string
    {
        return strtolower(pathinfo($this->original_name ?: $this->path, PATHINFO_EXTENSION));
    }
}
