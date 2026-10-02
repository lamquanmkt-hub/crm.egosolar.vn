<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'meta',
        'input_tokens',
        'output_tokens',
    ];

    protected $casts = [
        'meta' => 'array',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
