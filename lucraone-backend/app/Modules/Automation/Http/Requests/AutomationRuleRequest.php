<?php

namespace App\Modules\Automation\Http\Requests;

use App\Modules\Automation\Application\Actions\ActionRegistry;
use App\Modules\Automation\Domain\Operator;
use App\Modules\Automation\Domain\TriggerCatalog;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação compartilhada de regra de automação.
 *
 * Aqui a condição guardada como JSON é amarrada ao catálogo: campo precisa
 * existir no gatilho, operador precisa valer para o tipo do campo, e a
 * configuração da ação segue as regras que o próprio handler declara. É o que
 * impede a regra de virar consulta arbitrária ao banco.
 */
abstract class AutomationRuleRequest extends FormRequest
{
    public function rules(): array
    {
        $acao = (string) $this->input('action');

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'trigger' => ['required', Rule::in(TriggerCatalog::chaves())],
            'action' => ['required', Rule::in(ActionRegistry::chaves())],
            'active' => ['nullable', 'boolean'],

            'conditions' => ['nullable', 'array', 'max:10'],
            'conditions.*.campo' => ['required', 'string'],
            'conditions.*.operador' => ['required', Rule::in(Operator::todos())],
            'conditions.*.valor' => ['nullable', 'string', 'max:255'],

            ...ActionRegistry::existe($acao) ? ActionRegistry::regrasDeConfiguracao($acao) : [],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $gatilho = (string) $this->input('trigger');
            $acao = (string) $this->input('action');

            if (! TriggerCatalog::existe($gatilho)) {
                return;
            }

            if (ActionRegistry::existe($acao) && ! ActionRegistry::compativel($acao, $gatilho)) {
                $validator->errors()->add(
                    'action',
                    'esta ação precisa de um gatilho que carregue um produto: '
                    .implode(', ', array_map(
                        fn (string $chave) => TriggerCatalog::rotulo($chave),
                        TriggerCatalog::comProduto()
                    )).'.'
                );
            }

            foreach ((array) $this->input('conditions', []) as $indice => $condicao) {
                $this->validarCondicao($validator, $gatilho, $indice, (array) $condicao);
            }
        });
    }

    private function validarCondicao(Validator $validator, string $gatilho, int|string $indice, array $condicao): void
    {
        $campo = $condicao['campo'] ?? null;
        $operador = $condicao['operador'] ?? null;

        if (! $campo || ! TriggerCatalog::campoExiste($gatilho, $campo)) {
            $validator->errors()->add(
                "conditions.{$indice}.campo",
                'este campo não existe no gatilho escolhido.'
            );

            return;
        }

        $tipo = TriggerCatalog::tipoDoCampo($gatilho, $campo);

        if ($operador && ! Operator::aceito($tipo, $operador)) {
            $validator->errors()->add(
                "conditions.{$indice}.operador",
                "o operador escolhido não vale para um campo do tipo {$tipo}."
            );
        }

        if ($tipo === TriggerCatalog::TIPO_NUMERO && ! is_numeric($condicao['valor'] ?? null)) {
            $validator->errors()->add(
                "conditions.{$indice}.valor",
                'este campo espera um número.'
            );
        }
    }

    public function messages(): array
    {
        return [
            'name.required' => 'dê um nome para a regra.',
            'trigger.in' => 'gatilho desconhecido.',
            'action.in' => 'ação desconhecida.',
            'action_config.title.required' => 'informe o título do aviso.',
            'action_config.message.required' => 'informe a mensagem.',
            'action_config.recipients.required' => 'informe pelo menos um destinatário.',
            'action_config.subject.required' => 'informe o assunto do e-mail.',
            'action_config.amount.required' => 'informe o valor do ajuste.',
        ];
    }

    /**
     * Condições sem campo preenchido vêm do formulário quando o operador
     * adiciona uma linha e não usa — não são erro, são ruído a descartar.
     */
    protected function prepareForValidation(): void
    {
        $condicoes = collect((array) $this->input('conditions', []))
            ->filter(fn ($condicao) => ! empty($condicao['campo']))
            ->values()
            ->all();

        $this->merge(['conditions' => $condicoes]);
    }
}
