# syntax=docker/dockerfile:1.7

# =============================================================================
# LUCRAONE — imagem da aplicação (PHP 8.3 FPM)
#
# Dois alvos, mesma base:
#
#   dev   → código entra por bind mount, dev-deps instaladas, opcache desligado
#   prod  → código e assets embutidos, opcache ligado, sem dev-deps
#
#   docker compose build                       # usa target: dev
#   docker build --target prod -t lucraone .   # imagem publicável
#
# Os estágios `vendor`, `vendor-dev` e `assets` existem para que uma mudança em
# app/ não invalide o cache do composer nem do npm: só os manifestos entram
# antes do código.
# =============================================================================


# --- base --------------------------------------------------------------------
# Tudo o que dev e prod compartilham. O que for específico fica nos alvos finais,
# para não engordar a imagem publicada.
FROM php:8.3-fpm-alpine AS base

WORKDIR /app

# tini: sem um init o php-fpm assume PID 1 e não repassa SIGTERM aos workers —
# cada `docker compose down` esperaria os 10s de timeout antes do SIGKILL.
# libzip/icu: bibliotecas de runtime das extensões compiladas logo abaixo.
RUN apk add --no-cache \
        tini \
        curl \
        mysql-client \
        libzip

# As ferramentas de compilação (~150 MB) são removidas no mesmo RUN: em camadas
# separadas elas continuariam no histórico da imagem mesmo após o apk del.
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del --no-cache .build-deps \
    && rm -rf /tmp/pear

# pdo_sqlite e mbstring já vêm habilitados na imagem oficial; os testes usam
# sqlite :memory:, então não há extensão extra a instalar para rodar a suíte.

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-lucraone.ini


# --- vendor (produção) --------------------------------------------------------
# --no-scripts porque `package:discover` precisa do código da aplicação, que
# ainda não foi copiado; o discover roda depois, junto do autoloader otimizado.
FROM base AS vendor

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && php artisan package:discover --ansi


# --- vendor-dev ---------------------------------------------------------------
# Mesma coisa com phpunit, pint e phpstan. Só popula o volume de vendor do
# ambiente de desenvolvimento no primeiro `up`.
FROM base AS vendor-dev

COPY composer.json composer.lock ./
RUN composer install \
        --no-interaction \
        --no-scripts \
        --prefer-dist


# --- assets -------------------------------------------------------------------
# O painel usa @vite(); sem o manifest compilado toda página do sistema estoura.
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts

COPY . .
RUN npm run build


# --- dev ----------------------------------------------------------------------
FROM base AS dev

# Nenhum ENV com nome de configuração do Laravel neste estágio.
#
# O PHP CLI publica o ambiente do container em $_SERVER, e o Env do Laravel lê
# $_SERVER antes de tudo — antes inclusive do que o PHPUnit define com
# force="true", que só alcança getenv() e $_ENV. Um APP_ENV=local aqui faria
# app.env continuar "local" durante os testes, e o VerifyCsrfToken (que só se
# desliga em "testing") devolveria 419 em todo POST da suíte do painel.
#
# O valor certo vem de .env em desenvolvimento e do phpunit.xml nos testes.

COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/zz-dev.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# O bind mount do compose cobre /app, e o volume nomeado cobre /app/vendor.
# Este COPY é o que semeia esse volume na primeira subida: sem ele o primeiro
# `up` sobe sem autoload e o entrypoint precisaria de um composer install de
# vários minutos.
COPY --from=vendor-dev /app/vendor ./vendor

EXPOSE 9000
ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint"]
CMD ["php-fpm"]


# --- prod ---------------------------------------------------------------------
FROM base AS prod

ENV APP_ENV=production

COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/zz-prod.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Ordem importa: o código primeiro, depois os artefatos dos outros estágios,
# senão o COPY do código sobrescreveria vendor/ e public/build/.
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000
ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint"]
CMD ["php-fpm"]
