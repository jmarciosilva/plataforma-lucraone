<?php

namespace App\Modules\Terminals\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** Internal protocol record: lookups must bind to the locked Terminal. */
class TerminalCredentialOperation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['delivery_secret_hash', 'code_hash'];

    protected $casts = [
        'expires_at' => 'immutable_datetime',
        'consumed_at' => 'immutable_datetime',
        'confirmed_at' => 'immutable_datetime',
    ];
}
