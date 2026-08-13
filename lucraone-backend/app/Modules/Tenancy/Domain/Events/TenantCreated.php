<?php

namespace App\Modules\Tenancy\Domain\Events;

use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Tenant $tenant
    ) {
    }
}
