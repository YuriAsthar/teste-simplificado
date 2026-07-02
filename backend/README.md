# Wallet API

<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="300" alt="Laravel Logo">
</p>

Aplicação **API-only** em **Laravel 13** para carteira digital e transferências entre usuários. A API expõe endpoints JSON para autenticação e transferências, executada sobre PostgreSQL, Redis, RabbitMQ e Kafka.

---

## Visão Geral

- **Laravel 13.8+**, `laravel/sanctum ^4.3`.
- Requer **PHP ^8.3**; imagem Docker `php:8.4-fpm`.
- Driver de fila RabbitMQ (`vladimir-yuldashev/laravel-queue-rabbitmq ^15.0`).
- Produtor/consumidor Kafka via `mateusjunges/laravel-kafka ^2.11`.
- Nenhum build Node/Vite — projeto 100% API.

---

## Stack / Infraestrutura

Definida em [`docker-compose.yml`](./docker-compose.yml) dentro de `backend/`:

| Serviço | Imagem | Porta / Observação |
|---------|--------|--------------------|
| `app` | `php:8.4-fpm` (Dockerfile em `backend/`) | FPM 9000, monta `.` em `/var/www/html` |
| `web` | `nginx:1.25-alpine` | `${NGINX_HOST_PORT:-8080}:80` |
| `db` | `postgres:16` | banco `wallet_sandbox`, usuário `wallet_user` |
| `redis` | `redis:7-alpine` | cache e sessões |
| `rabbitmq` | `rabbitmq:3-management` | fila de jobs/notificações |
| `kafka` | `confluentinc/cp-kafka:7.7.1` | publicação de eventos de transferência |

> O `Dockerfile` e o `docker-compose.yml` ficam dentro de `backend/`.

---

## Endpoints

Todas as respostas são JSON. Rotas autenticadas exigem `Authorization: Bearer <token>`.

| Método | Endpoint | Autenticação | Descrição |
|--------|----------|--------------|-----------|
| GET | `/` | — | Health check leve (JSON) |
| GET | `/up` | — | Health check nativo do Laravel |
| POST | `/api/v1/auth/register` | — | Cria usuário, carteira e emite token |
| POST | `/api/v1/auth/login` | — | Emite token Sanctum a partir de e-mail/senha |
| POST | `/api/v1/auth/logout` | `auth:sanctum` | Revoga o token atual |
| POST | `/api/v1/transfer` | `auth:sanctum` | Executa transferência entre carteiras |

### Exemplos de payload

**Registrar usuário (`POST /api/v1/auth/register`)**

```json
{
  "name": "João Silva",
  "email": "joao@example.com",
  "password": "senhaSegura123",
  "type": "common",
  "document_country": "BRA",
  "document_type": "cpf",
  "document_value": "12345678909"
}
```

- `type`: `common` ou `merchant`.
- `document_country`: código alpha de 3 letras (ex.: `BRA`).
- Documentos brasileiros (`BRA`) validam CPF/CNPJ automaticamente.

**Transferir (`POST /api/v1/transfer`)**

```http
Idempotency-Key: <uuid-único>
Authorization: Bearer <token>
Content-Type: application/json
```

```json
{
  "payee_id": 2,
  "amount": 1000,
  "currency": "BRL"
}
```

- `amount` é um **inteiro em centavos** (`MoneyCast` valida apenas `int`).
- `Idempotency-Key` é obrigatório.

---

## Autenticação

- Tokens pessoais do **Laravel Sanctum**.
- Login valida contra o guard `web` (`AUTH_GUARD=web`).
- Ao registrar, o evento `UserCreated` dispara o listener `CreateUserWallet`, que cria automaticamente a carteira do usuário.

---

## Regras de Transferência

O serviço de transferência (`WalletTransferService`) executa dentro de uma transação com **lock pessimista** e valida:

- pagador e recebedor não podem ser o mesmo usuário;
- valor (`amount`) maior que zero;
- ambas as carteiras ativas;
- **lojista (`merchant`) não pode pagar**, apenas receber;
- pagador e recebedor devem usar a mesma moeda (`currency`);
- saldo suficiente na carteira do pagador;
- autorização externa via `util.devi.tools/api/v2/authorize`.

Transferências reprovadas são registradas com `status = failed` e um `failure_reason`.

---

## Fluxo Assíncrono / Outbox

Após uma transferência ser concluída:

1. Um registro `OutboxEvent` é criado com status `pending`.
2. O comando `php artisan outbox:publish` publica eventos pendentes no tópico Kafka `wallet.transfer.completed`.
3. Os comandos consumidores leem o tópico:
   - `kafka:consume-transfers`
   - `kafka:consume-retry-transfers`
4. O consumidor processa a mensagem e despacha `SendNotificationJob` na fila RabbitMQ.
5. A notificação é enviada para `util.devi.tools/api/v1/notify`.
6. Notificações falhas podem ser reprocessadas via `notifications:retry`.
7. Chaves de idempotência antigas são removidas por `cleanup:stale-idempotency-keys`.
8. `kafka:produce-transfer` é um comando auxiliar para debug/produção manual.

---

## Setup Local

1. Copie o `.env.example` de `backend/` para `.env` (define `NGINX_HOST_PORT`):

   ```bash
   cd backend
   cp .env.example .env
   ```

2. Suba a stack (o Dockerfile de `backend/` é usado):

   ```bash
   docker compose up -d --build
   ```

3. Gere a chave da aplicação:

   ```bash
   docker compose run --rm app php artisan key:generate
   ```

4. Execute as migrations:

   ```bash
   docker compose run --rm app php artisan migrate --force
   ```

> A correção manual de permissões (`chown`) não é necessária — o `Dockerfile` já define `www-data` corretamente.

---

## Comandos Úteis

> Todos os comandos Artisan e Composer devem rodar via `docker compose run --rm app` (nunca `docker compose exec`).

### Desenvolvimento

| Comando | Descrição |
|---------|-----------|
| `docker compose run --rm app composer dev` | Inicia servidor + worker de fila + `pail` (tail de logs) |
| `docker compose run --rm app composer test` | Executa suite PHPUnit |
| `docker compose run --rm app composer lint` | Verifica padrão de código (ECS) |
| `docker compose run --rm app composer lint-fix` | Aplica correções automáticas do ECS |
| `docker compose run --rm app composer stan` | Executa PHPStan |
| `docker compose run --rm app composer rector` | Executa Rector |
| `docker compose run --rm app composer phpmd` | Executa PHPMD |

### Artisan específicos

| Comando | Descrição |
|---------|-----------|
| `php artisan outbox:publish` | Publica eventos pendentes no Kafka |
| `php artisan kafka:consume-transfers` | Consome transferências concluídas do Kafka |
| `php artisan kafka:consume-retry-transfers` | Consome tópico de retry |
| `php artisan notifications:retry` | Reprocessa notificações falhas |
| `php artisan cleanup:stale-idempotency-keys` | Remove chaves de idempotência expiradas |
| `php artisan kafka:produce-transfer` | Comando de debug para produzir mensagem no Kafka |

---

## Ferramentas de Qualidade

- **ECS** (`symplify/easy-coding-standard`) — padrões de estilo.
- **PHPStan** + **Larastan** — análise estática.
- **Rector** — refatoração automatizada.
- **PHPMD** — detecção de código problemático.
- **PHPUnit 12** — testes unitários e de feature.

Não há build frontend; a CI roda apenas os scripts Composer listados acima.

---

## Modelos e Enums Principais

- **Models:** `User`, `Wallet` (`MoneyCast` no `balance`), `Transfer`, `IdempotencyKey`, `OutboxEvent`.
- **Enums:** `UserType`, `DocumentType`, `CurrencyType`, `TransferStatus`, `FailureReason`, `IdempotencyKeyStatus`, `OutboxStatus`, `AuthorizerResult`.

---

## Variáveis de Ambiente Relevantes

As principais já vêm preenchidas em `.env.example`:

| Variável | Significado |
|----------|-------------|
| `APP_URL` | `http://localhost:8080` |
| `AUTH_GUARD` | `web` (guard usado na autenticação) |
| `DB_CONNECTION` / `DB_HOST` / `DB_DATABASE` | PostgreSQL: `pgsql`, `db`, `wallet_sandbox` |
| `QUEUE_CONNECTION` | `rabbitmq` |
| `CACHE_STORE` | `redis` |
| `RABBITMQ_HOST` / `_PORT` / `_USER` / `_PASSWORD` | Configuração do RabbitMQ |
| `KAFKA_BROKERS` | `kafka:9092` |
| `KAFKA_TOPIC_COMPLETED` | `wallet.transfer.completed` |
| `KAFKA_TOPIC_DLQ` | `wallet.transfer.dlq` |
| `KAFKA_TOPIC_RETRY` | `wallet.transfer.retry` |
| `KAFKA_IDEMPOTENCY_TTL` | TTL de idempotência Kafka (segundos) |
| `KAFKA_RETRY_ATTEMPTS` / `KAFKA_RETRY_BACKOFF_SECONDS` | Retry do consumidor Kafka |

---

## Licença

O framework Laravel é software open-source licenciado sob [MIT license](https://opensource.org/licenses/MIT).
