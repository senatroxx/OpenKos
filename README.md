# OpenKOS

<!-- badges placeholder — add build status, PHP version, license badges when CI is set up -->

**Open-source property management platform for boarding houses (kos), apartments, and rentals.** Self-hosted, extensible, and built with Laravel + React.

OpenKOS handles the full rental lifecycle — properties, units, leases, tenants, invoices, payments, rent reminders, and maintenance — with a plugin architecture that lets you extend without forking.

## Features

- **Properties & Units** — Manage multiple properties with unit-level tracking, capacity, rates, and availability
- **Leases & Tenants** — Flexible leases with multi-tenant occupancy, billing intervals, and automated rent scheduling
- **Invoices & Payments** — Payment tracking with proof upload, manual confirmation, and overdue detection
- **Rent Reminders** — Automated WhatsApp reminders with configurable scheduling and manual one-off sends
- **Maintenance** — Ticket-based maintenance tracking with status workflow (reported → in-progress → resolved)
- **Multi-Property Support** — Dashboard, reports, and settings scoped per property
- **Role-Based Access** — Owner, admin, and staff roles with granular Spatie permissions
- **Plugin Architecture** — Extend via plugins (navigation, dashboard, settings, workspace tabs, notification drivers, payment gateways)
- **WhatsApp Integration** — Pluggable WhatsApp driver system with log, Baileys, and cloud API drivers

## Screenshots

<!-- screenshots placeholder — add dashboard, property, and lease screenshots -->

## Prerequisites

- **PHP 8.4+** with required extensions (BCMath, Ctype, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML)
- **Composer** (PHP package manager)
- **Node.js 22+** and **npm** (for frontend assets)
- **PostgreSQL 16+** (required — SQLite and MySQL are not tested)

## Quick Start

```bash
# 1. Install PHP dependencies
composer install

# 2. Set up environment
cp .env.example .env
php artisan key:generate
```

Edit `.env` to match your PostgreSQL setup — at minimum `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.

```bash
# 3. Run migrations
php artisan migrate

# 4. Install frontend dependencies
npm install

# 5. Generate TypeScript route functions
npm run wayfinder:generate --with-form

# 6. Build frontend assets
npm run build

# 7. Create the owner account
php artisan app:install
```

The `app:install` command will prompt for the owner's name, email, and password. Only one owner account can be created — subsequent runs will fail.

## Development

```bash
# Start all dev services (server, queue worker, logs, Vite)
composer run dev

# Or run individually:
php artisan serve --port=8080          # Laravel dev server
php artisan queue:listen --tries=1     # Queue worker (async jobs)
php artisan pail                       # Log viewer
npm run dev                            # Vite dev server with HMR

# Run tests
php artisan test --compact
```

## Plugin Development

OpenKOS has a plugin system that lets you register navigation items, dashboard pages, workspace tabs, settings pages, notification drivers, and payment gateways — all from a single plugin class without modifying core.

See [docs/platform.md](docs/platform.md) for the full guide, including manifests, versioning, registries, and the example plugin.

## Architecture

See [docs/architecture.md](docs/architecture.md) for layer conventions, data flow, domain model, state machines, and testing patterns.

Key decisions are recorded as Architecture Decision Records (ADRs) in [`docs/architecture/adr/`](docs/architecture/adr/README.md) — check there before making architectural changes.

## Project Status

**MVP / v0.1 Alpha** — actively developed. Core rental management flows are functional. Plugin system is foundation-level. Not yet production-hardened; expect breaking changes as the API stabilizes.

## Contributing

- **Report bugs & feature requests** — [Linear issue tracker](https://linear.app/openkos/issues)
- **Architecture changes** — include an ADR for decisions with lasting trade-offs (see ADR template and process in [`docs/architecture/adr/README.md`](docs/architecture/adr/README.md))
- **Plugin developers** — see [Plugin Development](#plugin-development) and [docs/platform.md](docs/platform.md)

## License

OpenKOS is open-source software licensed under the [Apache 2.0 license](LICENSE).
