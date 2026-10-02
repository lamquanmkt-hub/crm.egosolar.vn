<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyDocumentActivity extends Model
{
    protected $fillable = [
        'file_id',
        'folder_id',
        'action',
        'description',
        'meta',
        'user_id',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function file()
    {
        return $this->belongsTo(CompanyDocumentFile::class, 'file_id')->withTrashed();
    }

    public function folder()
    {
        return $this->belongsTo(CompanyDocumentFolder::class, 'folder_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
