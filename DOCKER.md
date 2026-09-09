# Ambiente Docker — LUCRAONE

Todo o desenvolvimento acontece em container. O PHP, o Composer e o Node do
Windows não são mais necessários.

## Subir

```bash
docker compose up -d --build
```

A primeira execução leva alguns minutos: compila as extensões PHP, instala as
dependências e prepara o banco. Depois disso, `docker compose up -d` sobe em
segundos.

| Endereço                | O quê                                        |
|-------------------------|----------------------------------------------|
| http://localhost:8000   | Painel administrativo e API                  |
| http://localhost:8025   | Mailpit — todo e-mail enviado cai aqui       |
| http://localhost:5173   | Vite (só os assets; não abra direto)         |
| `localhost:3306`        | MySQL, para DBeaver / TablePlus              |

Acompanhar a subida:

```bash
docker compose logs -f app
```

## Os containers

| Container            | Papel                                                        |
|----------------------|--------------------------------------------------------------|
| `lucraone-nginx`     | Serve estáticos e repassa PHP ao fpm                         |
| `lucraone-app`       | php-fpm — é quem aplica as migrations na subida              |
| `lucraone-queue`     | `queue:work redis` — automações e envio de e-mail            |
| `lucraone-scheduler` | `schedule:work` — resumo semanal e poda de automation_logs   |
| `lucraone-node`      | Vite em modo dev, com hot reload                             |
| `lucraone-mysql`     | MySQL 8.4                                                    |
| `lucraone-redis`     | Cache, fila e locks                                          |
| `lucraone-mailpit`   | SMTP falso + interface web                                   |

`app`, `queue` e `scheduler` são a **mesma imagem** com comandos diferentes.

## Comandos do dia a dia

Tudo roda dentro do container `app`:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
docker compose exec app php artisan tinker
docker compose exec app php artisan test

docker compose exec app ./vendor/bin/pint
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G

docker compose exec app composer require vendor/pacote
docker compose exec node npm install alguma-lib
```

Um shell, quando for mais prático:

```bash
docker compose exec app sh
```

## Situações comuns

**Alterei o `Dockerfile` ou o `composer.json`**

```bash
docker compose up -d --build
```

**Quero recomeçar do zero (apaga banco, redis e dependências)**

```bash
docker compose down -v
docker compose up -d --build
```

**As portas colidem com outro projeto**

Este host já tem os containers `loja-*` usando 3306, 6379, 1025 e 8025. Para
conviver com eles, crie um `.env` nesta pasta:

```
APP_PORT=8001
DB_PORT_HOST=3307
REDIS_PORT_HOST=6380
MAIL_PORT_HOST=1026
MAILPIT_UI_PORT=8026
```

**O CSS não atualiza / a página vem sem estilo**

O container `node` precisa estar de pé — é ele quem serve os assets em
desenvolvimento:

```bash
docker compose logs -f node
```

**Onde fica a configuração da aplicação**

Em `lucraone-backend/.env`, criado na primeira subida a partir do
`.env.example`. O `docker-compose.yml` **não** define variáveis do Laravel de
propósito: o PHP publica o ambiente do container em `$_SERVER`, que o Laravel lê
antes de qualquer outra fonte — e o `phpunit.xml` não consegue sobrepor `$_SERVER`.
Um `DB_CONNECTION=mysql` vindo do compose faria `artisan test` rodar contra o
banco de desenvolvimento e apagá-lo a cada teste.

A contrapartida: as credenciais aparecem em dois lugares. Ao trocar a senha do
banco, troque no `.env` **e** no serviço `mysql` do compose (ou defina
`DB_PASSWORD` num `.env` na raiz, que alimenta os dois).

**Conectar ao banco por fora do Docker**

De dentro dos containers o host é `mysql`. Do Windows é `127.0.0.1:3306` —
`mysql` não resolve fora da rede do compose.

## Por que o ambiente está afinado assim

O código chega ao container por bind mount do Windows, onde **cada leitura de
arquivo custa ~33 ms** — contra 0,08 ms no sistema de arquivos do container.
Três ajustes contornam isso, e removê-los faz o painel voltar a ~1,3 s por
página:

1. **opcache ligado em desenvolvimento** (`docker/php/php-dev.ini`). Parece
   errado, mas sem bytecode em cache o PHP recompila o framework inteiro a cada
   requisição. `validate_timestamps=1` mantém suas alterações aparecendo;
   `revalidate_freq=2` limita a conferência a uma vez a cada 2 s por arquivo.

2. **Artefatos gerados em volume nomeado** (`docker-compose.yml`): views Blade
   compiladas, cache do framework e `bootstrap/cache`. Nada disso é fonte —
   tudo é regerado por artisan. `storage/logs` e `storage/app` ficam de fora de
   propósito, para você abrir pelo Windows.

3. **`vendor/` e `node_modules/` em volume nomeado**, isolados do que o
   Composer e o npm do Windows deixaram na pasta.

O entrypoint faz `chown` desses volumes para `www-data`: eles nascem `root:root`
e os workers do php-fpm não conseguiriam gravar — o sintoma é HTTP 500 com
`tempnam(): file created in the system's temporary directory`.

### Se quiser mais velocidade

O ganho restante está em tirar o código do NTFS. Com o projeto dentro do
sistema de arquivos do WSL2 (`~/lucraone` no Ubuntu, em vez de `D:\`), a
leitura cai de 33 ms para 0,08 ms por arquivo. Exige ligar
**Docker Desktop → Settings → Resources → WSL Integration** para a distro e
editar via VS Code com a extensão WSL.

## Produção

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

Muda o seguinte: a imagem é construída no alvo `prod` (código e assets
embutidos, opcache ligado, sem dev-deps), não há bind mount nem Vite, o banco e
o Redis deixam de publicar porta, e os segredos passam a ser obrigatórios —
`APP_KEY`, `APP_URL`, `DB_PASSWORD`, `DB_ROOT_PASSWORD` e `MAIL_HOST` precisam
existir no ambiente ou a subida falha em vez de usar um valor padrão.

Gerar a chave:

```bash
docker compose exec app php artisan key:generate --show
```
