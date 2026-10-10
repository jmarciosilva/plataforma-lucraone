<?php

namespace Tests\Feature\Pdv;

use App\Modules\Terminals\Domain\Models\Terminal;
use App\Modules\Terminals\Domain\Models\TerminalPairingCode;
use App\Modules\Terminals\Domain\PairingCode;
use Illuminate\Support\Str;

/**
 * PDV-BE-05 — freio do pareamento, nas duas dimensões.
 *
 * Os três controles são complementares e nenhum basta sozinho:
 *
 * - `attempts` persistentes (PDV-BE-03) travam o ataque a UM código, mas não
 *   impedem varrer muitos códigos;
 * - o freio por IP trava o volume de uma origem, mas não um ataque distribuído;
 * - o freio por selector trava a insistência contra um alvo vindo de muitos
 *   IPs, e trocar de selector devolve o atacante ao freio por IP.
 *
 * O IP usado aqui chega por REMOTE_ADDR, que é o equivalente fiel da infra:
 * em produção o endereço real é acrescentado pelo nginx do host à direita do
 * X-Forwarded-For e o Symfony lê dessa ponta. Simular cliente por
 * X-Forwarded-For seria incoerente — ver PdvTestCase::IP_CLIENTE.
 */
class PdvPairingRateLimitTest extends PdvTestCase
{
    private const LIMITE_IP = 20;

    private const LIMITE_SELECTOR = 5;

    /**
     * Código bem formado com selector determinístico e segredo errado.
     *
     * Serve para gastar bucket sem acertar pareamento nenhum.
     */
    private function codigoComSelector(string $selector): string
    {
        return $selector.'.'.str_repeat('Z', 12);
    }

    private function selectorSintetico(int $n): string
    {
        $alfabeto = PairingCode::ALPHABET;
        $selector = '';
        for ($i = 0; $i < PairingCode::SELECTOR_LENGTH; $i++) {
            $selector .= $alfabeto[intdiv($n, max(1, strlen($alfabeto) ** $i)) % strlen($alfabeto)];
        }

        return $selector;
    }

    public function test_limite_por_ip_responde_429_no_excesso(): void
    {
        // Cada requisição usa um selector diferente, para que só o bucket de IP
        // se esgote — sem isso o freio por selector dispararia primeiro e o
        // teste não provaria nada sobre o IP.
        for ($i = 0; $i < self::LIMITE_IP; $i++) {
            $this->postPair([
                'pairing_code' => $this->codigoComSelector($this->selectorSintetico($i)),
                'installation_id' => (string) Str::uuid(),
            ])->assertStatus(422);
        }

        $excedente = $this->postPair([
            'pairing_code' => $this->codigoComSelector($this->selectorSintetico(self::LIMITE_IP)),
            'installation_id' => (string) Str::uuid(),
        ]);

        $this->assertErroPdv($excedente, 429, 'rate_limited');
        $this->assertSame('Muitas tentativas. Tente novamente mais tarde.', $excedente->json('error.message'));
    }

    public function test_429_traz_retry_after(): void
    {
        for ($i = 0; $i < self::LIMITE_IP; $i++) {
            $this->postPair([
                'pairing_code' => $this->codigoComSelector($this->selectorSintetico($i)),
                'installation_id' => (string) Str::uuid(),
            ]);
        }

        $excedente = $this->postPair([
            'pairing_code' => $this->codigoComSelector($this->selectorSintetico(99)),
            'installation_id' => (string) Str::uuid(),
        ])->assertStatus(429);

        $this->assertNotNull($excedente->headers->get('Retry-After'));
        $this->assertGreaterThan(0, (int) $excedente->headers->get('Retry-After'));
    }

    public function test_limite_por_selector_vale_mesmo_trocando_de_ip(): void
    {
        $emitido = $this->emitirPairing();
        $selector = PairingCode::selectorFrom($emitido->code);
        $this->assertNotNull($selector);

        $codigoErrado = $this->codigoComSelector($selector);

        // Cada tentativa de um IP diferente: o bucket de IP nunca se esgota
        // (1 de 20 em cada), então o que para a sexta é o bucket do selector.
        for ($i = 0; $i < self::LIMITE_SELECTOR; $i++) {
            $this->postPair(
                ['pairing_code' => $codigoErrado, 'installation_id' => (string) Str::uuid()],
                [],
                '198.51.100.'.(10 + $i)
            )->assertStatus(422);
        }

        $attemptsAntes = TerminalPairingCode::findOrFail($emitido->pairingId)->attempts;

        $excedente = $this->postPair(
            ['pairing_code' => $codigoErrado, 'installation_id' => (string) Str::uuid()],
            [],
            '198.51.100.200'
        );

        $this->assertErroPdv($excedente, 429, 'rate_limited');

        // O freio roda ANTES do serviço: a tentativa recusada por volume não
        // chega a tocar o código no banco.
        $this->assertSame(
            $attemptsAntes,
            TerminalPairingCode::findOrFail($emitido->pairingId)->attempts
        );
    }

    public function test_selectors_diferentes_nao_compartilham_bucket(): void
    {
        $outroTerminal = Terminal::factory()->create();
        $outroTerminal->tenant->update(['status' => 'ACTIVE', 'active' => true]);

        $a = $this->emitirPairing();
        $b = $this->emitirPairing($outroTerminal);

        $selectorA = PairingCode::selectorFrom($a->code);
        $selectorB = PairingCode::selectorFrom($b->code);
        $this->assertNotSame($selectorA, $selectorB);

        // Esgota o bucket do selector A, de IPs distintos.
        for ($i = 0; $i < self::LIMITE_SELECTOR; $i++) {
            $this->postPair(
                ['pairing_code' => $this->codigoComSelector($selectorA), 'installation_id' => (string) Str::uuid()],
                [], '198.51.100.'.(30 + $i)
            )->assertStatus(422);
        }

        $this->postPair(
            ['pairing_code' => $this->codigoComSelector($selectorA), 'installation_id' => (string) Str::uuid()],
            [], '198.51.100.201'
        )->assertStatus(429);

        // B segue livre: o freio é por alvo, não global.
        $this->postPair(
            ['pairing_code' => $b->code, 'installation_id' => (string) Str::uuid()],
            [], '198.51.100.202'
        )->assertOk();
    }

    public function test_codigo_sem_selector_extraivel_ainda_consome_bucket_de_ip(): void
    {
        // '0' não pertence ao alfabeto do pairing (sem 0/1/I/L/O/U), então não
        // há selector plausível. O código tem o tamanho certo, passa pela
        // validação estrutural e precisa continuar freado por IP — a
        // alternativa seria inventar chave a partir da string inteira, o que
        // colocaria o segredo no cache.
        $semSelector = '000000.ABCDEFGHJKMN';
        $this->assertNull(PairingCode::selectorFrom($semSelector));
        $this->assertSame(PairingCode::LENGTH, strlen($semSelector));

        for ($i = 0; $i < self::LIMITE_IP; $i++) {
            $this->postPair([
                'pairing_code' => $semSelector,
                'installation_id' => (string) Str::uuid(),
            ])->assertStatus(422);
        }

        $this->assertErroPdv(
            $this->postPair(['pairing_code' => $semSelector, 'installation_id' => (string) Str::uuid()]),
            429,
            'rate_limited'
        );
    }

    public function test_429_nao_revela_estado_do_servidor(): void
    {
        $emitido = $this->emitirPairing();
        $selector = PairingCode::selectorFrom($emitido->code);

        for ($i = 0; $i < self::LIMITE_SELECTOR; $i++) {
            $this->postPair(
                ['pairing_code' => $this->codigoComSelector($selector), 'installation_id' => (string) Str::uuid()],
                [], '198.51.100.'.(40 + $i)
            );
        }

        $corpo = $this->postPair(
            ['pairing_code' => $this->codigoComSelector($selector), 'installation_id' => (string) Str::uuid()],
            [], '198.51.100.203'
        )->assertStatus(429)->getContent();

        // Nada sobre existir selector, existir Terminal, ou quantas tentativas
        // restam no banco.
        foreach ([$selector, 'attempts', 'terminal', 'selector', 'remaining'] as $vazamento) {
            $this->assertStringNotContainsStringIgnoringCase((string) $vazamento, $corpo);
        }
    }

    public function test_pareamento_legitimo_nao_e_impedido_pelo_freio(): void
    {
        // Uma loja instalando alguns caixas na sequência precisa passar: o teto
        // por IP é mais alto que o por selector justamente por isso.
        $emitido = $this->emitirPairing();

        $this->postPair([
            'pairing_code' => $emitido->code,
            'installation_id' => (string) Str::uuid(),
        ])->assertOk();
    }
}
