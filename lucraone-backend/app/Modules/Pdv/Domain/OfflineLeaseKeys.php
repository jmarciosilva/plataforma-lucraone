<?php

namespace App\Modules\Pdv\Domain;

use SensitiveParameter;

/**
 * Material criptográfico do lease offline.
 *
 * Ed25519, do libsodium que já vem com a extensão `sodium` usada pelo projeto.
 * A escolha é por ser assimétrico — o Java precisa VERIFICAR sem poder
 * ASSINAR —, por ter assinatura curta e determinística, por não exigir escolha
 * de padding nem de curva no cliente, e por ter implementação madura em Java.
 *
 * `APP_KEY` está explicitamente fora: é segredo simétrico do servidor. Usá-la
 * para autorizar offline obrigaria a distribuí-la a cada caixa, e qualquer
 * caixa passaria a poder emitir a própria autorização.
 *
 * A validação é estrita de propósito: chave com tamanho errado, base64
 * inválido, par incoerente ou `key_id` ausente deixam o recurso DESLIGADO. Não
 * há fallback — nem assinatura simétrica, nem lease sem assinatura, nem
 * geração automática de chave. Um backend sem configuração criptográfica
 * simplesmente não emite autorização offline, e o caixa opera só online.
 */
final class OfflineLeaseKeys
{
    private function __construct(
        public readonly string $keyId,
        public readonly string $publicKeyBase64,
        #[SensitiveParameter] private readonly string $privateKey,
        public readonly string $publicKey,
    ) {}

    /**
     * O par configurado, ou null quando a configuração não permite assinar.
     */
    public static function fromConfig(): ?self
    {
        $keyId = config('pdv.offline.key_id');
        $private = self::decode(config('pdv.offline.private_key'));
        $public = self::decode(config('pdv.offline.public_key'));

        if (! is_string($keyId) || $keyId === '' || $private === null || $public === null) {
            return null;
        }

        if (strlen($private) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES
            || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return null;
        }

        // Par incoerente é erro de configuração silencioso: o backend assinaria
        // com uma chave que o Java não verifica, e a falha só apareceria no
        // caixa, offline. Conferir aqui transforma isso em "recurso desligado".
        if (! hash_equals(sodium_crypto_sign_publickey_from_secretkey($private), $public)) {
            return null;
        }

        return new self($keyId, base64_encode($public), $private, $public);
    }

    public function assinar(string $mensagem): string
    {
        return sodium_crypto_sign_detached($mensagem, $this->privateKey);
    }

    /**
     * Só para teste e diagnóstico: verifica com a chave pública, o mesmo
     * caminho que o Java percorre.
     */
    public function verificar(string $assinatura, string $mensagem): bool
    {
        return sodium_crypto_sign_verify_detached($assinatura, $mensagem, $this->publicKey);
    }

    private static function decode(mixed $valor): ?string
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }

        $decodificado = base64_decode($valor, true);

        return $decodificado === false ? null : $decodificado;
    }
}
