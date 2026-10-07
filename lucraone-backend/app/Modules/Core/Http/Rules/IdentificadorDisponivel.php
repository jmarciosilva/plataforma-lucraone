<?php

namespace App\Modules\Core\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Identificador de negócio livre, contando também os registros arquivados.
 *
 * Política do domínio (COR-01): **arquivar não libera o identificador**. SKU,
 * slug e e-mail continuam pertencendo ao registro histórico, e voltar a usá-los
 * é restaurar aquele registro — não criar outro. Os índices únicos do banco já
 * cobrem as linhas arquivadas, então esta regra não muda a resposta do banco:
 * ela chega antes, e explica.
 *
 * Existe porque `Rule::unique` responde a mesma pergunta mas não sabe dizer
 * *por que* o valor está ocupado. "Já existe" para um registro que não aparece
 * em nenhuma listagem era o sintoma que o COR-01 corrige: quem recebia a
 * mensagem ia procurar o registro e não encontrava.
 *
 * Consulta por `DB::table`, e não pelo model, de propósito: sem global scope de
 * estabelecimento nem de soft delete, a pergunta é exatamente a que o índice
 * único faz. O estabelecimento vem explícito, como em `BarcodeAvailable`.
 */
class IdentificadorDisponivel implements ValidationRule
{
    /**
     * @param  string  $tabela  tabela do índice único
     * @param  string  $coluna  coluna do identificador
     * @param  string  $entidade  como o registro se chama na mensagem ("produto")
     * @param  string  $campo  como o identificador se chama na mensagem ("SKU")
     * @param  bool  $feminino  concordância da mensagem ("uma categoria arquivada")
     * @param  string|null  $tenantId  null quando o índice é global, como em tenants
     * @param  string|null  $ignorarId  o próprio registro, nas edições
     */
    public function __construct(
        private string $tabela,
        private string $coluna,
        private string $entidade,
        private string $campo,
        private bool $feminino = false,
        private ?string $tenantId = null,
        private ?string $ignorarId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $ocupante = DB::table($this->tabela)
            ->where($this->coluna, $value)
            ->when($this->tenantId !== null, fn ($query) => $query->where('tenant_id', $this->tenantId))
            ->when($this->ignorarId !== null, fn ($query) => $query->where('id', '!=', $this->ignorarId))
            ->select('deleted_at')
            ->first();

        if ($ocupante === null) {
            return;
        }

        $artigo = $this->feminino ? 'uma' : 'um';
        $definido = $this->feminino ? 'a' : 'o';

        if ($ocupante->deleted_at === null) {
            $fail("já existe {$artigo} {$this->entidade} ".($this->feminino ? 'ativa' : 'ativo')." com este {$this->campo}.");

            return;
        }

        // A diferença que importa: dizer que está arquivado e o que fazer a
        // respeito. Sem isso o operador procura por algo que nenhuma listagem
        // mostra.
        $fail(
            "já existe {$artigo} {$this->entidade} ".($this->feminino ? 'arquivada' : 'arquivado')
            ." com este {$this->campo}. restaure {$definido} {$this->entidade} existente "
            ."ou use outro {$this->campo}."
        );
    }
}
