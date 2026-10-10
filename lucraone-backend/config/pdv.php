<?php

return [
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
];
