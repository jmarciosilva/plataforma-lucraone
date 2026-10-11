<?php

namespace Tests\Feature\Pdv;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Pdv\Application\OfflineLeaseIssuer;
use App\Modules\Pdv\Domain\Models\PdvOperatorBranchAssignment;
use App\Modules\Pdv\Domain\Models\PdvOperatorSession;
use App\Modules\Terminals\Domain\Models\Terminal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;

/**
 * Fase 5 — lease offline assinado.
 *
 * O backend EMITE o lease; quem o verifica é o cliente Java. Por isso os testes
 * daqui fazem as duas coisas: conferem o que o servidor publica e percorrem o
 * MESMO caminho de verificação que o Java vai percorrer, com a chave pública.
 * É assim que se prova que a assinatura cobre os bytes que o cliente recebe —
 * e que mexer em qualquer um deles invalida o lease.
 *
 * As chaves são efêmeras, geradas em cada teste. Nenhuma chave operacional é
 * gerada, publicada ou lida aqui, e o recurso nasce desligado em configuração.
 */
#[Group('pdv-operator')]
#[Group('pdv-offline')]
class PdvOperatorOfflineLeaseTest extends PdvOperatorTestCase
{
    private string $chavePublica;

    private string $chavePrivada;

    protected function setUp(): void
    {
        parent::setUp();

        $par = sodium_crypto_sign_keypair();
        $this->chavePublica = sodium_crypto_sign_publickey($par);
        $this->chavePrivada = sodium_crypto_sign_secretkey($par);
    }

    public function test_recurso_nasce_desligado_na_configuracao(): void
    {
        $this->assertFalse((bool) config('pdv.offline.enabled'));
        $this->assertNull(config('pdv.offline.private_key'));
        $this->assertNull(config('pdv.offline.public_key'));
        $this->assertFalse(app(OfflineLeaseIssuer::class)->habilitado());
    }

    public function test_desligado_por_flag_nao_emite_lease(): void
    {
        $this->configurarChaves(habilitado: false);

        $resposta = $this->login()->assertCreated();

        $resposta->assertJsonPath('data.offline.enabled', false)
            ->assertJsonMissingPath('data.offline.lease')
            ->assertJsonMissingPath('data.offline.public_key');
    }

    /**
     * Habilitado SEM material criptográfico: nada de fallback. O recurso
     * continua desligado em vez de assinar com um segredo simétrico ou de
     * emitir um lease sem assinatura.
     */
    public function test_habilitado_sem_chave_configurada_permanece_desligado(): void
    {
        config(['pdv.offline.enabled' => true, 'pdv.offline.key_id' => null,
            'pdv.offline.private_key' => null, 'pdv.offline.public_key' => null]);

        $this->login()->assertCreated()->assertJsonPath('data.offline.enabled', false);
        $this->assertFalse(app(OfflineLeaseIssuer::class)->habilitado());
    }

    public function test_par_de_chaves_incoerente_permanece_desligado(): void
    {
        $outroPar = sodium_crypto_sign_keypair();

        config([
            'pdv.offline.enabled' => true,
            'pdv.offline.key_id' => 'teste-1',
            'pdv.offline.private_key' => base64_encode($this->chavePrivada),
            // Pública de OUTRO par: o backend assinaria com uma chave que o
            // Java não verifica, e a falha só apareceria no caixa, offline.
            'pdv.offline.public_key' => base64_encode(sodium_crypto_sign_publickey($outroPar)),
        ]);

        $this->login()->assertCreated()->assertJsonPath('data.offline.enabled', false);
    }

    public function test_chave_de_tamanho_invalido_permanece_desligado(): void
    {
        config([
            'pdv.offline.enabled' => true,
            'pdv.offline.key_id' => 'teste-1',
            'pdv.offline.private_key' => base64_encode('curta'),
            'pdv.offline.public_key' => base64_encode('curta'),
        ]);

        $this->login()->assertCreated()->assertJsonPath('data.offline.enabled', false);
    }

    public function test_lease_valido_e_verificavel_com_a_chave_publica(): void
    {
        $this->configurarChaves();

        $resposta = $this->login()->assertCreated();
        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();

        $resposta->assertJsonPath('data.offline.enabled', true)
            ->assertJsonPath('data.offline.algorithm', 'Ed25519')
            ->assertJsonPath('data.offline.key_id', 'teste-1')
            ->assertJsonPath('data.offline.format_version', 1)
            ->assertJsonPath('data.offline.grace_minutes', 120)
            ->assertJsonPath('data.offline.public_key', base64_encode($this->chavePublica));

        $lease = $resposta->json('data.offline.lease');

        $this->assertTrue($this->verificar($lease), 'O lease precisa verificar com a chave pública publicada.');

        $payload = $this->payloadDo($lease);

        $this->assertSame($sessao->id, $payload['session_id']);
        $this->assertSame($this->operador->id, $payload['operator_id']);
        $this->assertSame($this->terminal->id, $payload['terminal_id']);
        $this->assertSame($this->terminal->installation_id, $payload['installation_id']);
        $this->assertSame($this->tenant->id, $payload['tenant_id']);
        $this->assertSame($this->terminal->company_id, $payload['company_id']);
        $this->assertSame($this->branch->id, $payload['branch_id']);
        $this->assertSame((string) $sessao->machine_token_id, $payload['machine_credential_reference']);
        $this->assertNotEmpty($payload['authorization_version']);
        $this->assertSame(['pdv:operator:session'], $payload['capabilities']);
        $this->assertSame(1, $payload['format_version']);
        $this->assertSame('teste-1', $payload['key_id']);
    }

    /**
     * O lease NÃO carrega segredo: nem senha, nem credencial de sessão, nem
     * credencial de máquina, nem hash de qualquer uma delas.
     */
    public function test_lease_nao_carrega_nenhum_segredo(): void
    {
        $this->configurarChaves();

        $resposta = $this->login()->assertCreated();
        $credencial = $resposta->json('data.credential.access_token');
        $lease = $resposta->json('data.offline.lease');
        $decodificado = json_encode($this->payloadDo($lease));

        foreach ([$credencial, hash('sha256', $credencial), self::SENHA, $this->maquina] as $segredo) {
            $this->assertStringNotContainsString($segredo, $lease);
            $this->assertStringNotContainsString($segredo, $decodificado);
        }

        $this->assertArrayNotHasKey('credential_hash', $this->payloadDo($lease));
    }

    /**
     * Prazo do lease: o MENOR entre a graça, a sessão e a credencial de
     * máquina. Com graça de 2 horas e sessão de 8, vence a graça.
     */
    public function test_prazo_do_lease_e_a_graca_quando_ela_e_a_menor(): void
    {
        $this->configurarChaves();

        $payload = $this->payloadDo($this->login()->assertCreated()->json('data.offline.lease'));

        $expira = strtotime($payload['expires_at']);

        $this->assertLessThanOrEqual(now()->addMinutes(120)->getTimestamp() + 2, $expira);
        $this->assertGreaterThan(now()->addMinutes(119)->getTimestamp(), $expira);
    }

    /**
     * Graça maior que a sessão não estende nada: a sessão é o teto.
     */
    public function test_lease_nunca_passa_do_prazo_da_sessao(): void
    {
        $this->configurarChaves();
        config(['pdv.offline.grace_minutes' => 10_000]);

        $payload = $this->payloadDo($this->login()->assertCreated()->json('data.offline.lease'));
        $sessao = PdvOperatorSession::withoutGlobalScopes()->sole();

        $this->assertLessThanOrEqual($sessao->expires_at->getTimestamp(), strtotime($payload['expires_at']));
    }

    /**
     * Nem do prazo da credencial de máquina: se a máquina vence antes, o lease
     * vence com ela.
     */
    public function test_lease_nunca_passa_do_prazo_da_credencial_de_maquina(): void
    {
        $this->configurarChaves();
        config(['pdv.offline.grace_minutes' => 10_000]);
        $this->terminal->tokens()->update(['expires_at' => now()->addMinutes(7)]);

        $payload = $this->payloadDo($this->login()->assertCreated()->json('data.offline.lease'));

        $this->assertLessThanOrEqual(now()->addMinutes(7)->getTimestamp() + 2, strtotime($payload['expires_at']));
    }

    /**
     * Qualquer byte alterado invalida o lease: é o que garante que a assinatura
     * cobre o contexto e as capacidades, e não só parte deles.
     */
    public function test_qualquer_alteracao_invalida_a_assinatura(): void
    {
        $this->configurarChaves();

        $lease = $this->login()->assertCreated()->json('data.offline.lease');
        [$dominio, $keyId, $corpo, $assinatura] = explode('.', $lease);

        $payload = json_decode($this->decodificar($corpo), true);

        $manipulacoes = [
            'capacidade ampliada' => fn () => $this->recompor($dominio, $keyId, ['capabilities' => ['pdv:sale:create']] + $payload, $assinatura),
            'filial trocada' => fn () => $this->recompor($dominio, $keyId, ['branch_id' => (string) Str::ulid()] + $payload, $assinatura),
            'estabelecimento trocado' => fn () => $this->recompor($dominio, $keyId, ['tenant_id' => (string) Str::ulid()] + $payload, $assinatura),
            'operador trocado' => fn () => $this->recompor($dominio, $keyId, ['operator_id' => (string) Str::ulid()] + $payload, $assinatura),
            'prazo estendido' => fn () => $this->recompor($dominio, $keyId, ['expires_at' => now()->addYear()->toIso8601ZuluString()] + $payload, $assinatura),
            'identificador de chave trocado' => fn () => $dominio.'.outra-chave.'.$corpo.'.'.$assinatura,
            'assinatura de outra chave' => fn () => $this->assinarComOutraChave($dominio, $keyId, $corpo),
        ];

        foreach ($manipulacoes as $nome => $manipular) {
            $this->assertFalse($this->verificar($manipular()), "Manipulação aceita indevidamente: {$nome}");
        }

        // E o original continua válido, provando que o teste não é vacuoso.
        $this->assertTrue($this->verificar($lease));
    }

    public function test_revalidar_emite_lease_novo_apos_reconexao(): void
    {
        $this->configurarChaves();

        $credencial = $this->abrirSessao();
        $primeiro = $this->payloadDo($this->consultarSessao($credencial)->json('data.offline.lease'));

        $this->travel(30)->minutes();
        $segundo = $this->payloadDo($this->revalidar($credencial)->assertOk()->json('data.offline.lease'));

        // A graça volta a contar do instante da reconexão.
        $this->assertGreaterThan(strtotime($primeiro['expires_at']), strtotime($segundo['expires_at']));
        $this->assertSame($primeiro['session_id'], $segundo['session_id']);
    }

    public function test_sessao_expirada_nao_recebe_lease(): void
    {
        $this->configurarChaves();

        $credencial = $this->abrirSessao();
        $this->travel(481)->minutes();

        $this->consultarSessao($credencial)->assertStatus(401);
        $this->revalidar($credencial)->assertStatus(401);
    }

    public function test_credencial_de_maquina_expirada_nao_recebe_lease(): void
    {
        $this->configurarChaves();

        $credencial = $this->abrirSessao();
        $this->terminal->tokens()->update(['expires_at' => now()->subMinute()]);

        $this->consultarSessao($credencial)->assertStatus(401);
    }

    public function test_conta_revogada_nao_recebe_lease(): void
    {
        $this->configurarChaves();

        $credencial = $this->abrirSessao();
        $this->operador->update(['status' => User::STATUS_INACTIVE]);

        $this->consultarSessao($credencial)->assertStatus(401);
    }

    public function test_atribuicao_revogada_nao_recebe_lease(): void
    {
        $this->configurarChaves();

        $credencial = $this->abrirSessao();
        PdvOperatorBranchAssignment::withoutGlobalScopes()
            ->where('user_id', $this->operador->id)
            ->update(['status' => PdvOperatorBranchAssignment::STATUS_REVOKED]);

        $this->consultarSessao($credencial)->assertStatus(401);
    }

    public function test_terminal_bloqueado_nao_recebe_lease(): void
    {
        $this->configurarChaves();

        $credencial = $this->abrirSessao();
        $this->terminal->update(['status' => Terminal::STATUS_BLOCKED]);

        $this->consultarSessao($credencial)->assertStatus(401);
    }

    /**
     * Uma revalidação recusada não move o marco da graça — portanto não estende
     * o prazo offline nem por engano.
     */
    public function test_revalidacao_recusada_nao_estende_a_graca(): void
    {
        $this->configurarChaves();

        $credencial = $this->abrirSessao();
        $marco = PdvOperatorSession::withoutGlobalScopes()->sole()->last_revalidated_at;

        $this->travel(20)->minutes();
        $this->revalidar(str_repeat('c', 64))->assertStatus(401);

        $this->assertTrue($marco->equalTo(PdvOperatorSession::withoutGlobalScopes()->sole()->last_revalidated_at));
    }

    /**
     * A versão de autorização muda quando a autorização efetiva muda. É o que
     * permite ao cliente offline perceber que o lease dele ficou velho.
     */
    public function test_versao_de_autorizacao_muda_quando_a_autorizacao_muda(): void
    {
        $this->configurarChaves();

        $credencial = $this->abrirSessao();
        $antes = $this->payloadDo($this->consultarSessao($credencial)->json('data.offline.lease'))['authorization_version'];

        // Reconcede a MESMA atribuição: o updated_at muda e a versão com ele.
        DB::table('pdv_operator_branch_assignments')->update(['updated_at' => now()->addMinute()]);

        $depois = $this->payloadDo($this->consultarSessao($credencial)->json('data.offline.lease'))['authorization_version'];

        $this->assertNotSame($antes, $depois);
    }

    /**
     * Pino do formato publicado no contrato Java.
     *
     * O documento de integração traz um test vector sintético — seed público,
     * payload e lease — para que quem implementa o verificador em Java tenha
     * algo concreto contra o que rodar. Este teste existe para que o vector não
     * envelheça em silêncio: se a ordem dos campos, o prefixo de domínio, o
     * alfabeto do base64url ou o texto assinado mudarem, ele falha aqui antes de
     * o cliente descobrir em campo.
     *
     * A chave é derivada de um seed CONSTANTE e PÚBLICO (0x2a repetido), e
     * existe apenas para este vector. Nenhuma chave operacional é gerada aqui.
     */
    public function test_formato_publicado_no_contrato_java_continua_valendo(): void
    {
        $par = sodium_crypto_sign_seed_keypair(str_repeat("\x2a", 32));

        $this->assertSame(
            'GX9rI+FshTLGq8g4+s1ep4m+DHaykgM0A5v6iz02jWE=',
            base64_encode(sodium_crypto_sign_publickey($par)),
            'A chave pública do test vector documentado mudou.',
        );

        $vector = 'pdv-offline-lease-v1.sample-2026-1.eyJmb3JtYXRfdmVyc2lvbiI6MSwia2V5X2lkIjoic2FtcGxlLTIwMjYtMSIsInNlc3Npb25faWQiOiIwMUs3QjBRUzNYSjdWNU05TjJINFQ2VzhZMCIsIm9wZXJhdG9yX2lkIjoiMDFLN0IwUVMzWEo3VjVNOU4ySDRUNlc4WTEiLCJ0ZXJtaW5hbF9pZCI6IjAxSzdCMFFTM1hKN1Y1TTlOMkg0VDZXOFkyIiwiaW5zdGFsbGF0aW9uX2lkIjoiM2YxYzlhNTItNmI3ZC00ZTE4LTljMGEtNWQyZThmNGIxYTY3IiwidGVuYW50X2lkIjoiMDFLN0IwUVMzWEo3VjVNOU4ySDRUNlc4WTMiLCJjb21wYW55X2lkIjoiMDFLN0IwUVMzWEo3VjVNOU4ySDRUNlc4WTQiLCJicmFuY2hfaWQiOiIwMUs3QjBRUzNYSjdWNU05TjJINFQ2VzhZNSIsIm1hY2hpbmVfY3JlZGVudGlhbF9yZWZlcmVuY2UiOiI0MjExIiwiYXV0aG9yaXphdGlvbl92ZXJzaW9uIjoiOWY4NmQwODE4ODRjN2Q2NTlhMmZlYWEwYzU1YWQwMTUiLCJjYXBhYmlsaXRpZXMiOlsicGR2Om9wZXJhdG9yOnNlc3Npb24iXSwiaXNzdWVkX2F0IjoiMjAyNi0xMC0xMVQxMjowMDowMFoiLCJleHBpcmVzX2F0IjoiMjAyNi0xMC0xMVQxNDowMDowMFoifQ.hV4EN28J6vbMRkO75aKQXq4kziSHRuhZbEP3jw_GwFz1FXL7JDZ9BksXA872UexC8U0pmEz7wP-7eo58uo-_DA';

        [$dominio, $keyId, $corpo, $assinatura] = explode('.', $vector);

        $this->assertTrue(
            sodium_crypto_sign_verify_detached(
                $this->decodificar($assinatura),
                $dominio.'.'.$keyId.'.'.$corpo,
                sodium_crypto_sign_publickey($par),
            ),
            'O lease do test vector documentado não verifica mais.',
        );

        // O lease real tem o mesmo prefixo de domínio e a MESMA ordem de campos
        // do vector. A ordem é parte do contrato porque a assinatura cobre bytes:
        // o cliente verifica o texto codificado e só então decodifica.
        $this->configurarChaves();
        $real = $this->login()->assertCreated()->json('data.offline.lease');

        $this->assertStringStartsWith('pdv-offline-lease-v1.', $real);
        $this->assertCount(4, explode('.', $real));
        $this->assertSame(
            array_keys(json_decode($this->decodificar($corpo), true)),
            array_keys($this->payloadDo($real)),
        );
    }

    private function configurarChaves(bool $habilitado = true): void
    {
        config([
            'pdv.offline.enabled' => $habilitado,
            'pdv.offline.key_id' => 'teste-1',
            'pdv.offline.private_key' => base64_encode($this->chavePrivada),
            'pdv.offline.public_key' => base64_encode($this->chavePublica),
        ]);
    }

    /**
     * O caminho de verificação do Java: separar, verificar a assinatura sobre
     * o texto codificado, e só então decodificar.
     */
    private function verificar(string $lease): bool
    {
        $partes = explode('.', $lease);

        if (count($partes) !== 4) {
            return false;
        }

        [$dominio, $keyId, $corpo, $assinatura] = $partes;

        if ($dominio !== 'pdv-offline-lease-v1' || $keyId !== 'teste-1') {
            return false;
        }

        return sodium_crypto_sign_verify_detached(
            $this->decodificar($assinatura),
            $dominio.'.'.$keyId.'.'.$corpo,
            $this->chavePublica,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadDo(string $lease): array
    {
        return json_decode($this->decodificar(explode('.', $lease)[2]), true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recompor(string $dominio, string $keyId, array $payload, string $assinatura): string
    {
        return $dominio.'.'.$keyId.'.'.$this->codificar((string) json_encode($payload, JSON_UNESCAPED_SLASHES)).'.'.$assinatura;
    }

    private function assinarComOutraChave(string $dominio, string $keyId, string $corpo): string
    {
        $outra = sodium_crypto_sign_keypair();

        return $dominio.'.'.$keyId.'.'.$corpo.'.'.$this->codificar(sodium_crypto_sign_detached(
            $dominio.'.'.$keyId.'.'.$corpo,
            sodium_crypto_sign_secretkey($outra),
        ));
    }

    private function codificar(string $bruto): string
    {
        return rtrim(strtr(base64_encode($bruto), '+/', '-_'), '=');
    }

    private function decodificar(string $codificado): string
    {
        return (string) base64_decode(strtr($codificado, '-_', '+/'), true);
    }
}
