<?php

return [
    'lifecycle' => [
        'pending_minutes' => 10,
        'recovery_minutes' => 10,
        'max_attempts' => 5,
    ],

    'pairing' => [
        'ttl_minutes' => 10,
        'max_attempts' => 5,
    ],

    /*
     * Credencial de máquina do Terminal (PDV-BE-04).
     *
     * ttl_minutes vale 720 porque 720 é o teto real, não uma preferência. O
     * Guard do Sanctum 4.3.3 valida as duas expirações com E lógico:
     *
     *   (! expiration || created_at > now - expiration) && (! expires_at || ! expires_at->isPast())
     *
     * O `expiration` global de config/sanctum.php (SANCTUM_EXPIRATION_MINUTES,
     * 720) é medido sobre created_at e se aplica a QUALQUER tokenable. Um
     * expires_at de máquina maior que 720 minutos seria ficção: o token morreria
     * no teto global de todo jeito. Declarar 720 mantém o que o projeto promete
     * igual ao que o Sanctum cumpre.
     *
     * Aumentar a validade de máquina exige remover esse teto global ou dar à
     * máquina um guard próprio — e remover o teto transformaria todo token
     * humano sem expires_at em token sem prazo. Isso é mudança de política de
     * sessão humana, fora do escopo do PDV-BE-04, e não foi feita.
     */
    'machine_credentials' => [
        'ttl_minutes' => 720,

        // Nome estável, não sensível, e específico o bastante para que a
        // revogação por tokenable nunca precise filtrar por nome.
        'token_name' => 'pdv-machine',
    ],

    /*
     * Sessão operacional do operador (Fase 5).
     *
     * Política explícita, versionada e configurável — não um número escondido
     * no serviço. Os três prazos são tetos, nunca pisos: a validade real de
     * uma sessão é o MENOR entre o prazo daqui, o prazo absoluto da própria
     * sessão e a expiração efetiva da credencial de máquina que a autenticou.
     * Uma sessão não pode sobreviver à máquina que a abriu.
     */
    'operator_sessions' => [
        // Oito horas: um turno de caixa. Prazo ABSOLUTO — não há renovação
        // deslizante, e revalidar não o estende. Terminar o turno exige novo
        // login, com senha, e é isso que mantém a sessão ligada a uma pessoa
        // presente em vez de a um computador ligado.
        'ttl_minutes' => 480,
    ],

    /*
     * Autorização offline.
     *
     * Desligada por padrão, e assim permanece até que a configuração
     * criptográfica exista. Sem par de chaves válido o recurso continua
     * desligado: não há fallback, nem assinatura simétrica, nem lease sem
     * assinatura. APP_KEY não é usada aqui — ela é segredo compartilhado do
     * servidor, e o Java precisa de uma chave PÚBLICA para verificar.
     */
    'offline' => [
        'enabled' => (bool) env('PDV_OFFLINE_LEASE_ENABLED', false),

        // Versão do formato do lease. Entra no texto assinado: trocar o
        // formato não pode fazer um lease antigo passar por novo.
        'format_version' => 1,

        // Identificador público da chave, para o Java escolher a chave certa
        // durante uma rotação. Não é segredo.
        'key_id' => env('PDV_OFFLINE_LEASE_KEY_ID'),

        // Chave privada Ed25519 (64 bytes, base64). SOMENTE no backend.
        'private_key' => env('PDV_OFFLINE_LEASE_PRIVATE_KEY'),

        // Chave pública Ed25519 (32 bytes, base64). Distribuída ao Java.
        'public_key' => env('PDV_OFFLINE_LEASE_PUBLIC_KEY'),

        // Duas horas desde a última revalidação online bem-sucedida. É o
        // prazo em que o caixa pode continuar operando sem alcançar o
        // servidor, nunca além da sessão nem da credencial de máquina.
        'grace_minutes' => 120,
    ],
];
