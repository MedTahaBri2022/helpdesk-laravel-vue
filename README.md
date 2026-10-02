# HelpDesk

A support ticket application: customers open tickets and follow the
conversation, agents take them, reply, leave internal notes and move them
through a workflow.

**Laravel 13 (PHP 8.3) · Sanctum · MySQL · Vue 3 · TypeScript · Pinia · Vue Router · Tailwind CSS · Docker**

[![CI](https://github.com/MedTahaBri2022/helpdesk-laravel-vue/actions/workflows/ci.yml/badge.svg)](https://github.com/MedTahaBri2022/helpdesk-laravel-vue/actions/workflows/ci.yml)

## Features

| Role | Can do |
| --- | --- |
| Customer | Register, open a ticket, see only their own tickets, reply, cancel a ticket, confirm a resolution or reopen |
| Agent / admin | See every ticket, search, filter and sort the queue, take and assign tickets, set the priority, reply, write internal notes, follow a dashboard |

## Architecture

```
api/   Laravel REST API
  app/Enums                 Role, TicketStatus (the workflow), TicketPriority
  app/Models                User, Ticket, Comment, Category (Eloquent, query scopes)
  app/Policies              TicketPolicy: who may view, update, comment, manage
  app/Http/Requests         one validation class per endpoint
  app/Http/Resources        JSON shape of the responses
  app/Http/Controllers/Api  Auth, Ticket, Comment, Reference (categories, agents, stats)
  database/                 migration, factories, demo seeder
  tests/                    30 feature and unit tests

web/   Vue 3 single-page application
  src/stores      Pinia: auth (session), tickets (list, filters, pagination)
  src/views       login, ticket list, ticket detail, new ticket, dashboard
  src/lib         API client (token, Laravel validation errors), types
```

## Design decisions

**Authorization in one place.** A `TicketPolicy` answers who may view, update or
comment, and query scopes (`visibleTo`) restrict lists. A customer asking for
someone else's ticket gets a `403`; internal notes are filtered by the same
scope in the list, the detail and the reply counter, so they cannot leak
through a forgotten endpoint.

**The workflow is data, owned by the API.** `TicketStatus::allowedFor($role)`
is the single description of which moves are legal. The API validates every
change against it and returns `allowed_statuses` with each ticket; the Vue
client only renders the buttons it is given and contains no workflow rule.

**Mass assignment cannot escalate privileges.** `role` is not fillable: a
registration payload containing `"role": "admin"` still creates a customer
(tested). A customer who sends `priority` or `assigned_to` gets a `403`, and a
ticket can only be assigned to a member of staff (validated in the database).

**Validation in Form Requests**, one class per endpoint, including the list
filters (`status`, `sort`, `per_page` capped at 50). Errors come back in
Laravel's standard `422` shape and are shown under the matching field.

**No N+1.** The ticket list eager-loads its three relations and counts replies
in the same query; a test fails if listing ten tickets takes more than eight
queries.

**Search treats input as data.** `%` and `_` typed by a user are escaped, with
an explicit `ESCAPE` clause because the default differs between MySQL and
SQLite (the test suite runs on both).

**Authentication.** Sanctum personal access tokens; login gives the same answer
for an unknown e-mail and a wrong password; logout revokes only the current
token; register and login are rate limited.

**On the client.** The auth store restores the session from the stored token
and drops it when the API answers `401`. The tickets store numbers its
requests so a slow response cannot overwrite a newer one, and the search box is
debounced.

## Run it locally

Requirements: Docker, Node.js 22+.

```bash
docker compose up -d --build          # MySQL + API on http://localhost:8000
docker compose exec api php artisan db:seed

cd web
npm install
npm run dev                           # http://localhost:5176
```

The first start installs the PHP dependencies inside a Docker volume and takes
a few minutes.

Demo accounts created by the seeder (password `password`, local use only):
`customer@helpdesk.example`, `agent@helpdesk.example`, `admin@helpdesk.example`.

## Tests

```bash
docker compose exec api php artisan test          # SQLite in memory
docker compose exec -e DB_CONNECTION=mysql -e DB_DATABASE=helpdesk_test \
  api php artisan test                            # the same suite on MySQL
docker compose exec api ./vendor/bin/pint --test  # code style

cd web
npm test                                          # stores and API client
npm run build                                     # type-check + production build
```

30 API tests (124 assertions): registration and its validation, privilege
escalation attempt, login, token revocation, ticket visibility per role,
filters, search, sorting and pagination, the query-count guard, assignment
rules, the status workflow for agents and customers, internal notes,
comments and the staff dashboard. 8 front-end tests on the stores and the API
client.

CI runs the API suite on SQLite and on MySQL, the style check, and the
front-end tests and build.

## API

| Method | Path | Access |
| --- | --- | --- |
| POST | `/api/register`, `/api/login` | public, rate limited |
| POST | `/api/logout` · GET `/api/me` | signed in |
| GET | `/api/tickets?q=&status=&priority=&category_id=&assigned=&sort=&page=&per_page=` | own tickets, or all for staff |
| POST | `/api/tickets` | signed in |
| GET | `/api/tickets/{id}` | requester or staff |
| PATCH | `/api/tickets/{id}` | requester (subject, description, allowed status) or staff |
| POST | `/api/tickets/{id}/comments` | requester or staff; `is_internal` staff only |
| GET | `/api/categories` | signed in |
| GET | `/api/agents`, `/api/stats` | staff |

## Limits

- The API container runs PHP's built-in development server. Production would
  use php-fpm behind nginx (or FrankenPHP) with a built image.
- Tokens are stored in `localStorage` by the SPA; cookie-based Sanctum
  authentication would be the stricter choice against XSS.
- No e-mail notifications, attachments or SLA timers.
- No end-to-end browser tests; the UI was checked by hand.

## License

MIT
