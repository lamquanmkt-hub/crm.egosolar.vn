<?php

namespace App\Models\Ai;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiToolLog extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'company_id',
        'tool_name',
        'arguments',
        'result_count',
        'duration_ms',
        'status',
        'error_message',
    ];

    protected $casts = [
        'arguments' => 'array',
        'result_count' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
