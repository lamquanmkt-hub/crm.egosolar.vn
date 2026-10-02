<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyDocumentVersion extends Model
{
    protected $fillable = [
        'file_id',
        'version_no',
        'original_name',
        'path',
        'mime',
        'size',
        'change_note',
        'uploaded_by',
    ];

    protected $casts = [
        'version_no' => 'integer',
        'size' => 'integer',
    ];

    public function file()
    {
        return $this->belongsTo(CompanyDocumentFile::class, 'file_id')->withTrashed();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
