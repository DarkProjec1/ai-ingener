<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmLog extends Model
{
    protected $fillable = [
        'participant_id',
        'user_message',
        'llm_response',
        'escalated',
        'tokens_used',
        'latency_ms',
        'model',
    ];

    protected $casts = [
        'llm_response' => 'array',
        'escalated' => 'boolean',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
