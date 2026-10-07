<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * SEC-06 — a suíte não pode depender de segredo real.
 *
 * O problema que originou o item: `lucraone-backend/.env.testing` estava
 * versionado com uma `APP_KEY` de formato real, num repositório público. A
 * correção não é esconder o valor — segredo publicado é segredo comprometido —,
 * é a suíte deixar de precisar de arquivo de ambiente e a chave de teste ser
 * explicitamente pública e exclusiva dos testes.
 *
 * Estes testes são a trava de regressão: se alguém reintroduzir um `.env` de
 * teste versionado, ou apontar a suíte para a chave de um ambiente real, eles
 * quebram.
 */
class SecretsConfigurationTest extends TestCase
{
    private function raiz(string $arquivo): string
    {
        return base_path($arquivo);
    }

    // ---------------------------------------------------------------
    // A suíte tem chave válida, e ela vem do phpunit.xml
    // ---------------------------------------------------------------

    public function test_suite_recebe_chave_de_criptografia_valida(): void
    {
        $chave = config('app.key');

        $this->assertIsString($chave);
        $this->assertStringStartsWith('base64:', $chave, 'a chave precisa estar no formato do Laravel');

        $bytes = base64_decode(substr($chave, 7), true);

        $this->assertNotFalse($bytes, 'a chave precisa ser base64 válido');
        $this->assertSame(32, strlen($bytes), 'AES-256-CBC exige 32 bytes');
    }

    public function test_criptografia_realmente_funciona_com_a_chave_da_suite(): void
    {
        // Não basta o formato: a chave precisa servir. Se a suíte rodasse sem
        // chave utilizável, qualquer teste que toque cookie ou sessão falharia
        // de forma obscura.
        $segredo = 'valor-de-teste-'.bin2hex(random_bytes(8));

        $this->assertSame($segredo, Crypt::decryptString(Crypt::encryptString($segredo)));
    }

    public function test_chave_da_suite_vem_do_phpunit_e_nao_de_arquivo_de_ambiente(): void
    {
        $phpunit = file_get_contents($this->raiz('phpunit.xml'));

        $this->assertMatchesRegularExpression(
            '/<env name="APP_KEY" value="base64:[^"]+" force="true"\/>/',
            $phpunit,
            'a APP_KEY da suíte precisa estar declarada no phpunit.xml, com force="true"'
        );

        preg_match('/<env name="APP_KEY" value="([^"]+)" force="true"\/>/', $phpunit, $achado);

        $this->assertSame(
            $achado[1],
            config('app.key'),
            'a chave em uso precisa ser a do phpunit.xml, não a de um arquivo .env'
        );
    }

    // ---------------------------------------------------------------
    // Nenhum arquivo de ambiente é necessário nem versionado
    // ---------------------------------------------------------------

    public function test_env_testing_nao_existe_mais(): void
    {
        $this->assertFileDoesNotExist(
            $this->raiz('.env.testing'),
            'a suíte não deve depender de .env.testing; a chave vive no phpunit.xml'
        );
    }

    public function test_chave_da_suite_e_diferente_da_chave_do_ambiente(): void
    {
        // Reusar a mesma chave em teste e em ambiente real foi exatamente o
        // erro do SEC-06. Se existir um .env na pasta — como existe na VPS —,
        // a chave dele não pode ser a da suíte.
        $env = base_path('.env');

        if (! is_file($env)) {
            // Clone limpo: não há .env, e a suíte roda só com o phpunit.xml.
            // É o cenário desejado, e o teste de formato já cobriu a chave.
            $this->assertTrue(true);

            return;
        }

        preg_match('/^APP_KEY=(.*)$/m', (string) file_get_contents($env), $achado);
        $doAmbiente = trim($achado[1] ?? '');

        if ($doAmbiente === '') {
            $this->assertTrue(true);

            return;
        }

        $this->assertNotSame(
            $doAmbiente,
            config('app.key'),
            'a chave da suíte não pode ser a mesma de um ambiente real'
        );
    }

    public function test_exemplo_de_ambiente_nao_traz_chave_pronta(): void
    {
        $exemplo = file_get_contents($this->raiz('.env.example'));

        $this->assertMatchesRegularExpression(
            '/^APP_KEY=\s*$/m',
            $exemplo,
            '.env.example precisa deixar APP_KEY vazia, para o ambiente gerar a sua'
        );
    }

    // ---------------------------------------------------------------
    // As travas de ignore
    // ---------------------------------------------------------------

    public function test_gitignore_cobre_qualquer_arquivo_de_ambiente(): void
    {
        $gitignore = file_get_contents($this->raiz('.gitignore'));

        $this->assertStringContainsString(".env.*\n", $gitignore, 'enumerar nomes deixou .env.testing passar');
        $this->assertStringContainsString("!.env.example\n", $gitignore, 'o exemplo precisa continuar versionável');
    }

    public function test_dockerignore_mantem_arquivos_de_ambiente_fora_da_imagem(): void
    {
        $dockerignore = file_get_contents($this->raiz('.dockerignore'));

        $this->assertStringContainsString(".env.*\n", $dockerignore, 'o stage prod faz COPY . . e levaria o arquivo para a imagem');
        $this->assertStringContainsString("!.env.example\n", $dockerignore, 'o entrypoint copia de .env.example quando não há .env');
    }
}
