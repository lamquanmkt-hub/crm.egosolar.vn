<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyDocumentFolder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'department',
        'parent_id',
        'name',
        'description',
        'created_by',
        'updated_by',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function files()
    {
        return $this->hasMany(CompanyDocumentFile::class, 'folder_id')->latest();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function activities()
    {
        return $this->hasMany(CompanyDocumentActivity::class, 'folder_id')->latest();
    }
}
