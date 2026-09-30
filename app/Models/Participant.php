<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participant extends Model
{
    protected $fillable = [
        'telegram_id',
        'username',
        'first_name',
        'last_name',
        'phone',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function openTicket()
    {
        return $this->tickets()->where('status', 'open')->latest()->first();
    }
}
