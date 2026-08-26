# Production Deployment

OpenKOS production uses the immutable FrankenPHP image published at
`ghcr.io/senatroxx/openkos`.

The production Compose reference contains only the application roles:

- `web`: FrankenPHP HTTP server on container port `8080`
- `queue`: database-backed Laravel queue worker
- `scheduler`: Laravel scheduler

PostgreSQL is external. Database-backed cache and sessions remain the default,
and production does not require Redis.

## Configuration

Create an untracked `.env.production` file with the normal Laravel settings,
including:

- `APP_KEY`
- `APP_URL`
- `APP_ENV=production`
- `APP_DEBUG=false`
- PostgreSQL connection settings
- `QUEUE_CONNECTION=database`
- `CACHE_STORE=database`
- `SESSION_DRIVER=database`
- SMTP settings
- `FILESYSTEM_DISK=local` with the Compose-managed upload volume for durable
  same-host uploads
- `TRUSTED_PROXIES` containing the reverse-proxy addresses or networks

## Branding storage verification

Logo and favicon files use `FILESYSTEM_DISK` and are streamed through the
application's branding routes, including when the disk is private. Automated
coverage uses Laravel's local fake disk; there is no remote-storage test
harness. Before enabling a remote or private production disk, manually verify
upload, replacement, removal, and the `Content-Type` returned by both branding
routes. Also verify that an Inertia navigation after a favicon change updates
the browser tab; this remains an integration check rather than a browser test.

The application is HTTP-only inside the Compose network. Terminate TLS in
Traefik, Caddy, Cloudflare Tunnel, or another external load balancer and
forward `X-Forwarded-For`, `X-Forwarded-Host`, `X-Forwarded-Port`, and
`X-Forwarded-Proto`. Configure `TRUSTED_PROXIES` only for proxies that are
actually trusted. `TRUSTED_PROXIES=*` is appropriate only when the application
is unreachable except through a trusted private proxy network.

## Release sequence

Migrations are an explicit one-shot release operation. They are never run by
web, queue, or scheduler startup:

```bash
docker compose -f compose.production.yaml run --rm web php artisan migrate --force
docker compose -f compose.production.yaml up -d
```

Each long-lived container initializes Laravel's runtime caches using the
configuration injected into that container before starting its process. This
keeps configuration-specific cache files in the actual runtime container and
avoids writing them into a disposable migration container.

The current route set supports route caching. If future routes use closures,
remove route caching from the runtime optimization step before deployment.

To deploy a specific immutable image:

```bash
OPENKOS_IMAGE=ghcr.io/senatroxx/openkos:1.2.3 \
docker compose -f compose.production.yaml up -d
```

The Compose file mounts the named `openkos_uploads` volume at
`storage/app/private` for tenant documents and payment proofs. The volume is
durable across container replacement on the same Docker host, but it is not a
cross-host or multi-replica filesystem. Back it up with the host's volume
backup process before replacing the host. If the application is later scaled
across hosts, move these uploads to shared object storage or shared storage
before doing so.

Runtime plugin packages use the same private persistent storage under
`storage/app/private/plugins`. Keep that path available to the web, queue, and scheduler
containers. After installing, enabling, disabling, or updating a runtime plugin, restart
FrankenPHP workers, queue workers, and the scheduler so each process boots the new plugin
set. Runtime plugin installation never changes the root Composer files.

## Image tags

Stable version tags publish `1.2.3`, `1.2`, `1`, and `latest`.

Nightly builds publish `nightly` and an immutable tag containing the UTC build
date and commit SHA. Nightly builds never move `latest`.
