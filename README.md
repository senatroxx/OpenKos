# OpenKOS

A self-hosted kos boarding house management application.

## Requirements

- PHP 8.4+
- Composer
- Docker (for local development services)

## Installation

```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy environment file
cp .env.example .env

# Start Docker services (PostgreSQL, Redis, Mailpit)
./vendor/bin/sail up -d

# Run database migrations
./vendor/bin/sail artisan migrate

# Build frontend assets
npm run build
```

## Initial Setup

After installation, create the owner account:

```bash
./vendor/bin/sail artisan app:install
```

This interactive command will prompt for the owner's name, email, and password. Only one owner account can be created — subsequent runs will fail.

## Development

```bash
# Start the dev server
./vendor/bin/sail up -d

# Run frontend dev server
npm run dev

# Run tests
./vendor/bin/sail artisan test --compact
```

## Architecture

- **Owner**: Full access to all features
- **Admin**: Operational access (properties, rooms, tenants, reports)
- **Staff**: Limited access (tenants, dashboard)

## Testing

```bash
# Run all tests
./vendor/bin/sail artisan test --compact

# Run specific test
./vendor/bin/sail artisan test --compact --filter=AuthorizationTest
```
