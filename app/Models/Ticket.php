<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $fillable = [
        'participant_id',
        'operator_id',
        'status',
        'subject',
        'last_user_message',
        'first_response_at',
        'closed_at',
    ];

    protected $casts = [
        'first_response_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function markFirstResponse(): void
    {
        if (!$this->first_response_at) {
            $this->update(['first_response_at' => now()]);
        }
    }

    public function close(): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    public function responseTimeSeconds(): ?int
    {
        if (!$this->first_response_at) {
            return null;
        }
        return $this->created_at->diffInSeconds($this->first_response_at);
    }
}
