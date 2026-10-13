# test-manager-library-3145556-api

> **Prueba técnica · Ficha ADSO 3145556**
> Entrega: **martes 13 de octubre de 2026, a las 5:00 p. m.** (17:00, hora Colombia).

Este repositorio es el **backend** de *Manager Library*, en **PHP con Laravel**. **Empieza vacío a propósito**: se construye en el fork de cada aprendiz.

## Instrucciones

Cada aprendiz debe **crear el fork** de los seis repositorios del proyecto y **resolver el proyecto
con el spec planteado**.

1. Hacer fork, a su cuenta de GitHub, de cada repositorio de la tabla del final.
2. Leer el spec en [`test-manager-library-3145556-docs`](https://github.com/code-sena/test-manager-library-3145556-docs).
   Se entrega en dos versiones: `spec-python/` y `spec-.net/`.
3. Desarrollar en los forks.

## El reto se desarrolla con React y PHP (Laravel)

El spec está escrito para Python y para .NET, pero el reto **no** se hace en esos lenguajes:

| Capa | Tecnología del reto |
|---|---|
| Frontend | React |
| Backend | PHP con Laravel |

Lo que el spec define sobre el negocio —historias, criterios de aceptación, reglas, contrato de la
API, modelo de datos— se respeta. Lo que define sobre la tecnología se traduce a React y Laravel.

## La prueba no consiste en escribir el código

El propósito principal es ver la **capacidad de desempeño con SDD** (*Spec-Driven Development*,
desarrollo guiado por especificación): cómo se lee, se interpreta y se aplica una especificación
para llevarla a un stack distinto. El código es el medio, no el fin.

## Entrega

Cuenta lo que quede **subido a los forks antes de las 5:00 p. m.** (17:00). Lo que llegue después no se revisa.
No hay prórroga: suban avances parciales con commits pequeños durante la prueba.

## Los seis repositorios

| Repositorio | Qué va ahí |
|---|---|
| [`test-manager-library-3145556-docs`](https://github.com/code-sena/test-manager-library-3145556-docs) | El spec: `spec-python/` y `spec-.net/` |
| [`test-manager-library-3145556-api`](https://github.com/code-sena/test-manager-library-3145556-api) | Backend en PHP (Laravel) |
| [`test-manager-library-3145556-app`](https://github.com/code-sena/test-manager-library-3145556-app) | Frontend en React |
| [`test-manager-library-3145556-page`](https://github.com/code-sena/test-manager-library-3145556-page) | Sitio público estático de presentación |
| [`test-manager-library-3145556-infra`](https://github.com/code-sena/test-manager-library-3145556-infra) | Contenedores, red, volúmenes y motor de base de datos vacío |
| [`test-manager-library-3145556-tool`](https://github.com/code-sena/test-manager-library-3145556-tool) | Utilidades: sembrador de datos de demostración |

---

# Implementation notes (English)

Backend of *Manager Library*: **PHP 8.3 + Laravel 13**, hexagonal architecture, PostgreSQL 16 (schema `library`).
Spec: `docs/spec-python/` (business rules, API contract and data model are respected as written).

## Architecture

```text
app/
├── Domain/                  # pure PHP, readonly objects, no Illuminate, no third party (Art. I)
│   ├── Book, Loan, LoanItem, Member, User, Category   + value objects Isbn, Quantity, DocumentNumber, DateRange
│   └── Errors/              # BookNotAvailable, MemberHasOverdue, MemberLimitExceeded, ... (code + Spanish message)
├── Application/             # knows only Domain (Art. II)
│   ├── Ports/               # Clock, IdGenerator, PasswordHasher, TokenIssuer, CoverStorage,
│   │                        # BookRepository, MemberRepository, UserRepository, LoanRepository, ReportReader
│   └── Services/            # one use case per class: LendBooksService, ReturnItemService, OverdueService, ...
├── Adapters/
│   ├── Http/                # controllers, middleware (auth, role, CORS), ErrorRenderer, Presenter
│   ├── Persistence/         # Query Builder repositories (the only place with SQL)
│   ├── Security/            # Argon2id hasher, JWT HS256 issuer (firebase/php-jwt)
│   ├── Storage/             # covers on disk
│   ├── System/              # SystemClock (CLOCK_OFFSET_DAYS), UUID generator
│   └── Console/             # library:create-admin
└── Bootstrap/PortBindingsServiceProvider.php   # the single composition root (Art. III)
```

## How to run

Everything runs in Docker (the host needs neither PHP nor Composer).

```bash
# Full stack (database + api + app) from the infra repo:
cd ../infra && cp .env.example .env && docker compose up --build

# Only this API against your own PostgreSQL 16:
docker build -t manager-library-api .
docker run --rm -p 8000:8000 --network <net> \
  -e DB_HOST=<db host> -e DB_DATABASE=library -e DB_USERNAME=library -e DB_PASSWORD=library \
  -e JWT_SECRET=<at least 32 chars> -e ADMIN_USERNAME=admin -e ADMIN_PASSWORD=<password> \
  -e CORS_ORIGIN=http://localhost:5173 manager-library-api
```

On start the container (`docker/entrypoint.sh`) waits for the database, runs `php artisan migrate --force`
(an empty database is enough), creates the admin from `ADMIN_USERNAME`/`ADMIN_PASSWORD` if missing
(`php artisan library:create-admin`, idempotent) and serves `0.0.0.0:8000`. Restarting is safe.

| Variable | Meaning |
|---|---|
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | PostgreSQL connection (search path `library,public`) |
| `JWT_SECRET` | HS256 key, at least 32 characters |
| `ADMIN_USERNAME`, `ADMIN_PASSWORD` | initial admin (created by the startup command) |
| `COVERS_DIR` | where covers are stored; if unset or not writable it falls back to `storage/covers` |
| `CORS_ORIGIN` | the single origin allowed by CORS |
| `CLOCK_OFFSET_DAYS` | shifts the API clock by whole days (default `0`); see below |

`CLOCK_OFFSET_DAYS` exists only so the demo seeder can create overdue loans: start the API with `-20`, create loans,
restart with `0` and those loans are overdue. It is read only by `SystemClock`; the domain never sees it.

## Tests

```bash
# 1. PostgreSQL for tests (own container and network)
docker network create ml-net
docker run -d --name ml-test-db --network ml-net -p 55432:5432 -e POSTGRES_USER=library \
  -e POSTGRES_PASSWORD=library -e POSTGRES_DB=library_test postgres:16-alpine
# 2. dependencies and image (once)
docker run --rm -v "$PWD:/app" -w /app composer:2 install
docker build -t ml-api-dev .
# 3. run
./run-tests.sh vendor/bin/phpunit                     # everything
./run-tests.sh vendor/bin/phpunit --testsuite Unit    # domain + use cases with fakes, no database
```

Suites: `Unit` (domain and application with a fake `Clock` and in-memory fakes), `Integration` (schema, repositories,
security, covers), `Feature` (HTTP contract walk), `Architecture` (layer rules).

## Dependencies

Runtime: `laravel/framework` (from the skeleton), `firebase/php-jwt` (the JWT port adapter). Dev: `phpunit/phpunit` and
the skeleton dev packages. No Sanctum, Passport or permission packages. `composer.lock` is committed.

## Translation decisions

| Topic | Decision |
|---|---|
| Stack | Python/.NET spec translated to PHP 8.3 + Laravel; the `import-linter` contracts become a PHPUnit architecture test (T-19) |
| Names | Spec names kept: tables `category, book, member, loan, loan_item, user` (schema `library`), classes `Book, Loan, LoanItem, ...`, methods `withdraw, restock, addItem, returnItem`, services `LendBooksService, ...` |
| Immutability | Domain objects are `readonly`; `withdraw()`, `restock()`, `addItem()` return new objects (`ItemAdded`, `ItemReturned`) instead of mutating |
| DDL | Raw SQL inside Laravel migrations (no `DEFAULT`, no timestamps, partial indexes, named CHECKs); the Laravel default migrations (users, cache, jobs) were removed. The bookkeeping table is explicitly `public.migrations`, so restarts are idempotent |
| Concurrency (ADR-002) | The spec version witness has no column in `data-model.md`, so it is `UPDATE book SET available_copies=? WHERE id=? AND available_copies=<value read>`; 0 rows -> retry (up to 3) -> `409 BOOK_NOT_AVAILABLE`. `ck_book_available_range` is the last barrier. Returns use a relative `available_copies + 1` guarded by `returned_at IS NULL` |
| Book edit | A `total_copies` change shifts `available_copies` by the same difference in SQL (`available_copies + (new_total - total_copies)`) |
| Auth | JWT HS256 (`sub`, `role`, `iat`, `exp`), 8 h; Argon2id via `password_hash`; password minimum 8 characters (spec silent) |
| Error body | Single `{code, message, details}` for everything, including 401/403/404/405/413/500 (no traces). Codes not in the contract: `UNAUTHENTICATED` (401, missing or invalid token), `METHOD_NOT_ALLOWED` (405), `INTERNAL_ERROR` (500). Messages are Spanish |
| Validation | Malformed input -> `422 VALIDATION_ERROR` with `details[{field, issue}]`; `size` > 50 is rejected with 422 (not clamped); more than 3 items in a loan body -> `VALIDATION_ERROR` |
| `days_late` | Whole days rounded up (any overdue line shows at least 1); 0 when not overdue |
| Overdue endpoint | Paginated like the others (`items,page,size,total`), default `size` 50; sorted by most days late |
| Status filter | `GET /loans?status=` is evaluated in SQL (`open`, `overdue`, `closed`); the status is never stored |
| Report range | `from`/`to` are dates, both inclusive (upper bound is the start of the next day, UTC) |
| Covers | `GET /books/{id}/cover` implemented (cited in the text, missing in the table); needs a token; type decided by magic bytes plus image decode; keys are `uuid.png` or `uuid.jpg`; removal nulls the key first, then deletes the file |
| Router | `/loans/overdue` is registered before `/loans/{id}` |
| Roles | Role checks run before any resource lookup, so a 403 never reveals whether a resource exists |
| Docker | `php:8.3-cli` with `pdo_pgsql`, `artisan serve` on 8000 (enough for the exercise); PHP accepts 4 MB uploads so the application answers 422 for covers over 2 MB |

## Task status

| Task | Status | Evidence |
|---|---|---|
| T-01 skeleton + `/health` | done | `tests/Feature/HealthTest.php`; container boots with one command |
| T-02 migrations + 5 categories | done | `tests/Integration/SchemaTest.php` (tables, no DEFAULT, timestamptz, FKs, partial ISBN index, fixed category ids, idempotent re-migrate) |
| T-03..T-06 domain | done | `tests/Unit/Domain/*` (26 tests, fake clock) |
| T-07..T-11 use cases | done | `tests/Unit/Application/*` (22 tests, in-memory fakes): HU-01..HU-10, last-copy conflict, frozen report |
| T-12, T-13 persistence | done | `tests/Integration/AdaptersTest.php` against PostgreSQL 16, incl. RNF-2 at repository level (two stale loans on the last copy: one wins, one `ConcurrentUpdate`) |
| T-14 security | done | `tests/Integration/SecurityAndStorageTest.php` (Argon2id, JWT claims and expiry); the contract test checks no `password` in responses |
| T-15 covers | done | same file: 2 MB PNG ok, 3 MB rejected, text renamed `.png` rejected |
| T-16 HTTP | done | `tests/Feature/ContractTest.php` walks every route and every code of api-contract section 2 |
| T-17 boot + admin | done | `ContractTest` (idempotent `library:create-admin` + login); real stack: `docker compose up` on an empty volume migrates, creates the admin, login 200, restart is a no-op; demo seeder ran end to end (3 overdue lines, report with rows) |
| T-18 CHECKs | done | `SchemaTest` (`ck_book_total_positive`, `ck_user_role` reject bad inserts) |
| T-19 architecture | done | `tests/Architecture/LayerDependencyTest.php` (fails on a forbidden `use`, proven by a self-test of the checker) |
| RNF-2 over HTTP | done | `scripts/rnf2-concurrency.sh` (two simultaneous `POST /loans` for the last copy against the running stack; 6 consecutive runs: exactly one 201 and one 409; the container runs `artisan serve` with 4 workers) plus the repository-level test in `AdaptersTest` |
| RNF-1 (5 000 books < 300 ms) | pending | not measured; search uses the `(category_id, title)` index and `ILIKE` |
