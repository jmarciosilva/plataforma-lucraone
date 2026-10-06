<?php

namespace App\Modules\Products\Http\Concerns;

use App\Modules\Products\Domain\Models\Product;
use Illuminate\Validation\Rule;

/** Validação estrutural cadastral; não confirma enquadramento ou vigência fiscal. */
trait ValidatesFiscalClassification
{
    protected function fiscalClassificationRules(): array
    {
        // Nullable continua permitido; requiredIf impede tratar só espaços como ausência.
        return [
            'ncm_code' => ['sometimes', 'nullable', Rule::requiredIf(fn () => $this->input('ncm_code') !== null), 'string', 'regex:/\A[0-9]{8}\z/'],
            'cest_code' => ['sometimes', 'nullable', Rule::requiredIf(fn () => $this->input('cest_code') !== null), 'string', 'regex:/\A[0-9]{7}\z/'],
            'default_origin_code' => ['sometimes', 'nullable', Rule::requiredIf(fn () => $this->input('default_origin_code') !== null), 'string', 'size:1', Rule::in(array_keys(Product::DEFAULT_ORIGINS))],
        ];
    }

    protected function fiscalClassificationMessages(): array
    {
        return [
            'ncm_code.required' => 'Informe o NCM com exatamente 8 dígitos, sem pontuação.',
            'ncm_code.string' => 'Informe o NCM com exatamente 8 dígitos, sem pontuação.',
            'ncm_code.regex' => 'Informe o NCM com exatamente 8 dígitos, sem pontuação.',
            'cest_code.required' => 'Informe o CEST com exatamente 7 dígitos, sem pontuação.',
            'cest_code.string' => 'Informe o CEST com exatamente 7 dígitos, sem pontuação.',
            'cest_code.regex' => 'Informe o CEST com exatamente 7 dígitos, sem pontuação.',
            'default_origin_code.required' => 'Selecione um código de origem padrão disponível.',
            'default_origin_code.string' => 'Selecione um código de origem padrão disponível.',
            'default_origin_code.size' => 'Selecione um código de origem padrão disponível.',
            'default_origin_code.in' => 'Selecione um código de origem padrão disponível.',
        ];
    }
}
