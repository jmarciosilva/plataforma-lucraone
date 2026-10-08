<?php

namespace App\Modules\Terminals\Domain\Models;

use App\Modules\Core\Domain\Traits\HasUlid;
use Database\Factories\TerminalPairingCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminalPairingCode extends Model
{
    use HasFactory, HasUlid;

    protected $fillable = ['terminal_id', 'selector', 'code_hash', 'expires_at', 'attempts', 'consumed_at', 'invalidated_at'];

    protected $hidden = ['code_hash'];

    protected $attributes = ['attempts' => 0];

    protected $casts = [
        'id' => 'string',
        'attempts' => 'integer',
        'expires_at' => 'immutable_datetime',
        'consumed_at' => 'immutable_datetime',
        'invalidated_at' => 'immutable_datetime',
    ];

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    protected static function newFactory(): TerminalPairingCodeFactory
    {
        return TerminalPairingCodeFactory::new();
    }
}
