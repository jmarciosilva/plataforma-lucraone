#!/bin/sh
#
# Prepara o container antes de entregar o processo principal.
#
# Roda nos três containers da aplicação (fpm, fila, agendador), então tudo o que
# não pode acontecer três vezes em paralelo fica atrás de uma variável:
# só o serviço `app` recebe AUTO_MIGRATE=true.

set -e

cd /app

log() { echo "[entrypoint] $*"; }

# --- .env --------------------------------------------------------------------
# As credenciais de infraestrutura chegam pelo environment do compose e vencem
# o arquivo (o Dotenv do Laravel é imutável: não sobrescreve o que já existe).
# O .env ainda é necessário para APP_KEY e para o que não vem do compose.
if [ ! -f .env ]; then
    log ".env ausente — criando a partir de .env.example"
    # Via arquivo temporário e rename: os três containers sobem quase juntos e
    # um cp direto poderia ser lido pela metade por outro deles.
    cp .env.example ".env.$$" && mv -n ".env.$$" .env
    rm -f ".env.$$"
fi

if [ -z "${APP_KEY}" ] && ! grep -q '^APP_KEY=base64:' .env; then
    log "gerando APP_KEY"
    php artisan key:generate --force --no-ansi
fi

# --- dependências ------------------------------------------------------------
# O volume nomeado é semeado pela imagem no primeiro `up`. Este ramo cobre o
# caso de alguém ter removido o volume à mão.
if [ ! -f vendor/autoload.php ]; then
    log "vendor vazio — instalando dependências (demora na primeira vez)"
    composer install --no-interaction --prefer-dist
fi

# --- diretórios graváveis ----------------------------------------------------
# O .dockerignore não leva o conteúdo de storage/, e o git não versiona pasta
# vazia: em um clone limpo estes caminhos simplesmente não existem.
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    storage/app/public \
    bootstrap/cache

# Bind mount do Windows ignora chmod; o `|| true` evita que isso derrube a
# subida por um erro que não afeta nada.
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

# Os workers do php-fpm rodam como www-data. Nos caminhos que vêm de volume
# nomeado (views compiladas, cache do framework, bootstrap/cache) o diretório
# nasce root:root, e sem este chown o fpm devolve 500 em toda requisição — o
# bind mount não mostra o problema porque o Docker Desktop entrega tudo
# gravável. Rodar como root aqui é o que permite corrigir; o alvo de produção
# já sobe com a posse certa desde o build.
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data         storage/framework         storage/logs         bootstrap/cache 2>/dev/null || true
fi

# --- banco -------------------------------------------------------------------
# O healthcheck do compose já segura o start, mas ele responde quando o daemon
# aceita conexão — o MySQL ainda leva alguns segundos preparando o schema
# interno na primeira subida.
# WAIT_FOR_DB_HOST, e não DB_HOST: nenhuma variável com nome de configuração do
# Laravel entra no ambiente deste container. O PHP CLI publica o ambiente em
# $_SERVER, que o Env do Laravel lê antes de tudo — e o `force` do PHPUnit não
# alcança $_SERVER. Uma DB_CONNECTION aqui faria a suíte de testes rodar contra
# o MySQL de desenvolvimento em vez do sqlite em memória.
if [ "${WAIT_FOR_DB:-true}" = "true" ] && [ -n "${WAIT_FOR_DB_HOST}" ]; then
    log "aguardando ${WAIT_FOR_DB_HOST}:${WAIT_FOR_DB_PORT:-3306}"

    tentativas=0
    until php -r 'exit(@fsockopen(getenv("WAIT_FOR_DB_HOST"), (int) (getenv("WAIT_FOR_DB_PORT") ?: 3306), $e, $s, 2) ? 0 : 1);' 2>/dev/null; do
        tentativas=$((tentativas + 1))
        if [ "${tentativas}" -ge 60 ]; then
            log "banco não respondeu em 60s — abortando"
            exit 1
        fi
        sleep 1
    done

    log "banco disponível"
fi

if [ "${AUTO_MIGRATE:-false}" = "true" ]; then
    log "aplicando migrations"
    php artisan migrate --force --no-ansi

    # Symlink public/storage. Em bind mount do Windows a criação pode falhar,
    # e falhar aqui não justifica impedir a aplicação de subir.
    # --force porque o symlink sobrevive entre subidas (vive no bind mount) e
    # sem ele o comando sai com erro em toda reinicialização.
    php artisan storage:link --force --no-ansi >/dev/null 2>&1         || log "storage:link ignorado"
fi

# --- caches ------------------------------------------------------------------
# Em produção o custo de resolver config e rotas a cada request não se paga.
# Em desenvolvimento o cache é justamente o que faz uma alteração de rota não
# aparecer, então lá ele fica desligado — e limpo, caso tenha sobrado do host.
if [ "${APP_ENV}" = "production" ]; then
    log "gerando cache de config, rotas e views"
    php artisan config:cache --no-ansi
    php artisan route:cache --no-ansi
    php artisan view:cache --no-ansi
else
    php artisan config:clear --no-ansi >/dev/null 2>&1 || true
    php artisan route:clear --no-ansi >/dev/null 2>&1 || true
fi

log "pronto — executando: $*"

exec "$@"
