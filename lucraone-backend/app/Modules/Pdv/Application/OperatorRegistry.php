<?php

namespace App\Modules\Pdv\Application;

use App\Modules\Authorization\Domain\Models\Permission;
use App\Modules\Authorization\Domain\Models\Role;
use App\Modules\Branches\Domain\Models\Branch;
use App\Modules\Identity\Domain\Models\TenantUser;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Domain\Exceptions\OperatorSessionFailed;
use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;
use App\Modules\Pdv\Domain\Models\PdvOperatorProfile;
use App\Modules\Pdv\Domain\OperatorPermission;
use App\Modules\Pdv\Domain\OperatorUsername;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro e administração dos operadores de caixa de um estabelecimento.
 *
 * Orquestra quatro coisas que já existiam separadas e precisam andar juntas: a
 * identidade (`users`), o vínculo (`tenant_user`), o perfil de caixa
 * (`pdv_operator_profiles`) e a atribuição às filiais
 * (`pdv_operator_branch_assignments`, reutilizada via OperatorBranchAccess e
 * NÃO duplicada aqui).
 *
 * Duas portas de entrada, com autoridades diferentes de propósito:
 *
 *   - `criar()` cria uma identidade NOVA. Isso é mexer no cadastro global de
 *     pessoas, então exige `manage-users` além da autoridade de operador;
 *   - `vincular()` dá perfil de caixa a quem JÁ existe no LucraOne. Não toca em
 *     nome, e-mail, senha nem status da conta — só acrescenta o papel
 *     operacional e as filiais. É o caminho do gerente que também vai operar o
 *     caixa, e do contador que atende duas lojas.
 *
 * A senha é da identidade global, e é por isso que redefini-la é a operação mais
 * restrita daqui: exige que a pessoa pertença EXCLUSIVAMENTE a este
 * estabelecimento. Quem é compartilhado com outro tenant não tem a senha
 * redefinida por um administrador local — ele veria a senha temporária e com ela
 * entraria no outro estabelecimento.
 */
class OperatorRegistry
{
    /**
     * Papel operacional provisionado sob demanda.
     *
     * Não entra no StandardRoleMatrix de propósito: lá ele seria criado em todo
     * estabelecimento, inclusive nos que nunca vão usar PDV, e passaria a
     * aparecer na tela de permissões de todos. Aqui ele nasce no primeiro
     * operador cadastrado, com `access-pdv` e nada mais.
     *
     * Criar o papel não concede acesso a ninguém: ele só vale para quem é
     * explicitamente atribuído a ele, e mesmo assim só opera nas filiais em que
     * tiver atribuição ativa.
     */
    public const PAPEL = 'pdv-operator';

    public function __construct(
        private OperatorDelegation $delegacao,
        private OperatorBranchAccess $filiais,
        private OperatorSessionLifecycle $sessoes,
        private OperatorSessionAudit $auditoria,
    ) {}

    /**
     * Cria uma pessoa nova e já a habilita como operadora.
     *
     * @param  array{name: string, username: string, password: string, email?: string|null, branch_ids?: array<int, string>}  $dados
     *
     * @throws OperatorSessionFailed
     * @throws ValidationException
     */
    public function criar(User $ator, string $tenantId, array $dados): PdvOperatorProfile
    {
        $this->delegacao->exigir($ator, null, $tenantId);

        // Identidade nova é cadastro de pessoa, não configuração de caixa.
        if (Gate::forUser($ator)->denies('create', User::class)) {
            throw OperatorSessionFailed::proibido();
        }

        return DB::transaction(function () use ($ator, $tenantId, $dados) {
            $pessoa = new User([
                'id' => (string) Str::ulid(),
                // Null é um valor legítimo: caixa de lanchonete não tem e-mail,
                // e fabricar um seria pior que não ter.
                'email' => $dados['email'] ?? null,
                'password' => $dados['password'],
            ]);

            $pessoa->forceFill([
                'name' => $dados['name'],
                'status' => User::STATUS_ACTIVE,
                // Sem e-mail não há o que verificar. Com e-mail, o cadastro
                // administrativo já o considera confirmado, como no fluxo de
                // usuários do painel.
                'email_verified_at' => ($dados['email'] ?? null) === null ? null : now(),
            ])->save();

            $perfil = $this->habilitar($ator, $tenantId, $pessoa, $dados, OperatorSessionAudit::OPERATOR_CREATED);

            return $perfil;
        }, 3);
    }

    /**
     * Habilita como operadora uma pessoa que já existe.
     *
     * @param  array{username: string, branch_ids?: array<int, string>}  $dados
     *
     * @throws OperatorSessionFailed
     * @throws ValidationException
     */
    public function vincular(User $ator, string $tenantId, User $pessoa, array $dados): PdvOperatorProfile
    {
        $this->delegacao->exigir($ator, $pessoa, $tenantId);

        // Conta arquivada ou inativa não ganha caixa por aqui: restaurar ou
        // reativar é decisão sobre a identidade global, tomada na tela de
        // usuários, por quem tem autoridade sobre ela.
        //
        // Vínculo ativo é exigido ANTES de qualquer gravação, e é a condição
        // que separa este caminho do `criar()`: lá o vínculo é criado junto da
        // pessoa, porque a pessoa está nascendo; aqui, criar vínculo
        // transformaria a tela de caixa num jeito de trazer para dentro do
        // estabelecimento alguém que nunca foi convidado. Quem convida é a tela
        // de usuários.
        //
        // A recusa é a MESMA para conta inativa, conta arquivada e pessoa de
        // outro estabelecimento: distinguir confirmaria a existência de uma
        // conta que este administrador não deveria poder sondar.
        $indisponivel = $pessoa->trashed()
            || ! $pessoa->isActive()
            || $pessoa->membershipFor($tenantId)?->status !== TenantUser::STATUS_ACTIVE;

        if ($indisponivel) {
            throw ValidationException::withMessages([
                'user_id' => 'esta conta não está disponível para ser habilitada no caixa.',
            ]);
        }

        if (PdvOperatorProfile::para($tenantId, $pessoa->id) !== null) {
            throw ValidationException::withMessages([
                'user_id' => 'esta pessoa já é operadora deste estabelecimento.',
            ]);
        }

        return DB::transaction(
            fn () => $this->habilitar($ator, $tenantId, $pessoa, $dados, OperatorSessionAudit::OPERATOR_LINKED),
            3,
        );
    }

    /**
     * Altera o que pertence ao PERFIL: username e status.
     *
     * Nome, e-mail e senha são da identidade global e têm caminhos próprios —
     * `atualizarIdentidade()` e `redefinirSenha()` —, com autoridade própria.
     * Misturar tudo num único update faria a autoridade mais fraca arrastar a
     * mais forte.
     *
     * @throws OperatorSessionFailed
     * @throws ValidationException
     */
    public function atualizarPerfil(User $ator, PdvOperatorProfile $perfil, string $username, string $status): PdvOperatorProfile
    {
        $this->delegacao->exigir($ator, $perfil->user, $perfil->tenant_id);

        return DB::transaction(function () use ($ator, $perfil, $username, $status) {
            $anterior = $perfil->username;
            $statusAnterior = $perfil->status;

            $this->aplicarUsername($perfil, $username);
            $perfil->status = $status;
            $perfil->save();

            if ($perfil->username !== $anterior) {
                $this->auditoria->perfil(OperatorSessionAudit::OPERATOR_USERNAME_CHANGED, $perfil, $ator, [
                    'previous_username' => $anterior,
                ]);
            }

            if ($perfil->status !== $statusAnterior) {
                $this->auditoria->perfil(
                    $perfil->isActive()
                        ? OperatorSessionAudit::OPERATOR_ACTIVATED
                        : OperatorSessionAudit::OPERATOR_DEACTIVATED,
                    $perfil,
                    $ator,
                    ['previous_status' => $statusAnterior],
                );
            }

            if ($perfil->username === $anterior && $perfil->status === $statusAnterior) {
                $this->auditoria->perfil(OperatorSessionAudit::OPERATOR_UPDATED, $perfil, $ator);
            }

            // Desativar precisa derrubar o turno em andamento na mesma
            // transação. A revalidação por requisição já recusaria na próxima
            // chamada; antecipar aqui fecha a janela em que um lease offline
            // emitido antes continuaria valendo.
            if (! $perfil->isActive()) {
                $this->sessoes->revokeForOperator($perfil->tenant_id, $perfil->user_id, $ator, 'operator-deactivated');
            }

            return $perfil;
        }, 3);
    }

    /**
     * Nome e e-mail da identidade global.
     *
     * Exige autoridade sobre a identidade — e a UserPolicy só a concede quando a
     * pessoa pertence exclusivamente a este estabelecimento. Alguém
     * compartilhado com outro tenant tem o cadastro em modo leitura aqui, e a
     * recusa é explícita para a tela não parecer que salvou.
     *
     * @throws OperatorSessionFailed
     * @throws ValidationException
     */
    public function atualizarIdentidade(User $ator, PdvOperatorProfile $perfil, string $nome, ?string $email): void
    {
        $pessoa = $perfil->user;
        $this->delegacao->exigir($ator, $pessoa, $perfil->tenant_id);

        if ($pessoa->name === $nome && $pessoa->email === $email) {
            return;
        }

        if (Gate::forUser($ator)->denies('manageGlobalIdentity', [$pessoa, $perfil->tenant_id])) {
            throw ValidationException::withMessages([
                'name' => 'os dados da conta desta pessoa não podem ser alterados por este estabelecimento.',
            ]);
        }

        $pessoa->update(['name' => $nome, 'email' => $email]);

        $this->auditoria->perfil(OperatorSessionAudit::OPERATOR_UPDATED, $perfil, $ator, [
            'identity_changed' => true,
        ]);
    }

    /**
     * Gera e devolve uma senha temporária.
     *
     * A senha pertence à identidade global, então a autoridade exigida é a mais
     * forte do módulo: `manageGlobalIdentity`, que só vale para quem pertence
     * exclusivamente a este estabelecimento, somada à contenção de delegação.
     * Sem isso, um administrador de uma loja redefiniria a senha de um contador
     * que também atende outra — e, com a senha temporária na tela, entraria lá.
     *
     * @throws OperatorSessionFailed
     */
    public function redefinirSenha(User $ator, PdvOperatorProfile $perfil): string
    {
        $pessoa = $perfil->user;
        $this->delegacao->exigir($ator, $pessoa, $perfil->tenant_id);

        if (Gate::forUser($ator)->denies('manageGlobalIdentity', [$pessoa, $perfil->tenant_id])) {
            throw OperatorSessionFailed::proibido();
        }

        $temporaria = Str::random(12);
        $pessoa->update(['password' => $temporaria]);

        // Senha temporária NUNCA entra em auditoria nem em log. O registro diz
        // que houve redefinição e quem a fez; o valor volta só na resposta, uma
        // vez, para quem tem autoridade para vê-lo.
        $this->auditoria->perfil(OperatorSessionAudit::OPERATOR_PASSWORD_RESET, $perfil, $ator);

        return $temporaria;
    }

    /**
     * Deixa as filiais do operador exatamente iguais à lista informada.
     *
     * Concessão e revogação passam pelo OperatorBranchAccess, que já aplica a
     * autoridade por filial, a coerência estrutural, a auditoria e a revogação
     * em cascata das sessões. Reimplementar isso aqui criaria um segundo caminho
     * de concessão — e um deles acabaria sem alguma dessas garantias.
     *
     * @param  array<int, string>  $filiaisDesejadas
     *
     * @throws OperatorSessionFailed
     */
    public function sincronizarFiliais(User $ator, PdvOperatorProfile $perfil, array $filiaisDesejadas): void
    {
        $tenantId = $perfil->tenant_id;
        $desejadas = array_values(array_unique(array_map('strval', $filiaisDesejadas)));

        $atuais = PdvOperatorBranchAssignment::consultaIsolada($tenantId)
            ->where('user_id', $perfil->user_id)
            ->where('status', PdvOperatorBranchAssignment::STATUS_ACTIVE)
            ->pluck('branch_id')
            ->all();

        foreach (array_diff($desejadas, $atuais) as $branchId) {
            $this->filiais->grant($ator, $perfil->user, $this->filial($tenantId, $branchId));
        }

        foreach (array_diff($atuais, $desejadas) as $branchId) {
            $this->filiais->revoke($ator, $perfil->user, $this->filial($tenantId, $branchId));
        }
    }

    /**
     * O papel operacional do estabelecimento, criado na primeira necessidade.
     *
     * Idempotente: rodar de novo não duplica papel, permissão nem concessão.
     */
    public function papelDeOperador(string $tenantId): Role
    {
        $papel = Role::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => self::PAPEL],
            ['id' => (string) Str::ulid(), 'description' => 'Operador de caixa (PDV)']
        );

        $permissao = Permission::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('name', OperatorPermission::ACESSO)
            ->first();

        // A permissão vem do catálogo do estabelecimento. Se ela não existir, o
        // estabelecimento não foi provisionado — criar a permissão aqui
        // esconderia esse problema e divergiria do ProvisionarEstabelecimento.
        if ($permissao === null) {
            throw OperatorSessionFailed::proibido();
        }

        $papel->grantPermission($permissao);

        return $papel;
    }

    /**
     * Operadores do estabelecimento, para a listagem administrativa.
     *
     * @return Collection<int, PdvOperatorProfile>
     */
    public function doEstabelecimento(string $tenantId): Collection
    {
        return PdvOperatorProfile::consultaIsolada($tenantId)->with('user')->orderBy('username')->get();
    }

    /**
     * Passos comuns a criar e vincular.
     *
     * @param  array{username: string, branch_ids?: array<int, string>}  $dados
     */
    private function habilitar(User $ator, string $tenantId, User $pessoa, array $dados, string $acao): PdvOperatorProfile
    {
        $perfil = new PdvOperatorProfile([
            'tenant_id' => $tenantId,
            'user_id' => $pessoa->id,
            'status' => PdvOperatorProfile::STATUS_ACTIVE,
            'created_by' => $ator->id,
        ]);

        $this->aplicarUsername($perfil, $dados['username']);
        $perfil->save();

        // Vínculo ativo e papel operacional. `joinTenant` preserva o vínculo de
        // quem já era da loja, e `assignRole` acrescenta sem remover os outros
        // papéis — um gerente que passa a operar o caixa continua gerente.
        $pessoa->joinTenant($tenantId, TenantUser::STATUS_ACTIVE);
        $pessoa->assignRole($this->papelDeOperador($tenantId), $tenantId);

        $this->sincronizarFiliais($ator, $perfil, $dados['branch_ids'] ?? []);

        $this->auditoria->perfil($acao, $perfil->fresh(), $ator);

        return $perfil->fresh();
    }

    /**
     * Normaliza, valida e confere unicidade antes de gravar.
     *
     * A unicidade é conferida aqui além do índice do banco porque a mensagem
     * importa: o administrador precisa saber que o nome já existe, e não receber
     * um erro de integridade. O índice continua sendo a garantia final, para o
     * caso de dois cadastros simultâneos.
     *
     * @throws ValidationException
     */
    private function aplicarUsername(PdvOperatorProfile $perfil, string $username): void
    {
        if (! OperatorUsername::valido($username)) {
            throw ValidationException::withMessages([
                'username' => 'use de 3 a 32 caracteres, apenas letras, números, ponto, hífen ou sublinhado.',
            ]);
        }

        $canonico = OperatorUsername::canonical($username);

        $conflito = PdvOperatorProfile::consultaIsolada($perfil->tenant_id)
            ->where('username_canonical', $canonico)
            ->when($perfil->exists, fn ($query) => $query->whereKeyNot($perfil->getKey()))
            ->exists();

        if ($conflito) {
            throw ValidationException::withMessages([
                'username' => 'este nome de usuário já está em uso neste estabelecimento.',
            ]);
        }

        $perfil->definirUsername($username);
    }

    /**
     * @throws OperatorSessionFailed
     */
    private function filial(string $tenantId, string $branchId): Branch
    {
        $filial = Branch::withoutGlobalScopes()
            ->whereKey($branchId)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($filial === null) {
            throw OperatorSessionFailed::proibido();
        }

        return $filial;
    }
}
