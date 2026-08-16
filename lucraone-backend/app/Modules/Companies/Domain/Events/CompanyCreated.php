<?php

namespace App\Modules\Companies\Domain\Events;

use App\Modules\Companies\Domain\Models\Company;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CompanyCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Company $company
    ) {}
}
