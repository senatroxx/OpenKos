# Platform Layer & Plugin System

> **Status:** Foundation shipped and consumed (v0.1 Alpha) — registrations render in the sidebar, settings nav, and workspace tabs; plugins carry manifests, subscribe to domain events, and ship their own routes/migrations
> **Purpose:** The plugin extension strategy — registries, the OpenKOS manager/facade, the plugin lifecycle, manifest/versioning/dependency rules, domain events, and extension boundaries. For general layer conventions see `docs/architecture.md`.

## Why

OpenKOS is becoming an extensible platform. Plugins must be able to add navigation items, dashboard pages, workspace tabs, settings pages, notification drivers, and payment gateways — without forking the app. This layer is the seam that makes that possible. It was introduced additively: no schema changes, no UI changes, no domain rewrites.

## Namespace & Location

The reusable platform code lives in the standalone `openkos/platform` Composer package published on Packagist. The application consumes the released package through Composer, while the main repository keeps only application-owned plugins in `src/Plugins/`:

```
openkos-platform/
├── src/Core/                 Contracts, DTOs, and platform-level events
└── src/Platform/              Registries, manager, facade, and plugin lifecycle

openkos/src/Plugins/
├── WhatsApp/                 Built-in plugin: registers application drivers
└── Example/                  Reference plugin — disabled by default
```

The package exposes host-agnostic contracts and events such as `OpenKOS\Core\Events\PaymentRecorded`. OpenKOS may also dispatch its legacy `App\Events` counterparts for application subscribers; the package never imports application events or models.

Composer package auto-discovery registers `PlatformServiceProvider`; the application does not list it again in `bootstrap/providers.php`. The host merges explicit plugin classes from `config/platform.php` with trusted Composer-discovered plugins when `platform.discovery.enabled` is true.

## The Registries

Nine singletons, each bound as a **container singleton** in `PlatformServiceProvider::register()`. All implement `Arrayable`, so exposing any of them to the frontend later is `->toArray()` in a shared Inertia prop.

| Registry               | Registers                                        | Item shape                                                                                                                                                                                                                     |
| ---------------------- | ------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `NavigationRegistry`   | Sidebar nav items, grouped (`main`, `footer`, …) | `NavigationItem(title, href?, icon?, permission?, children[])` — mirrors the TS `NavItem` type                                                                                                                                 |
| `DashboardRegistry`    | Dashboard pages                                  | `DashboardPage(key, title, href, permission?)`                                                                                                                                                                                 |
| `WorkspaceRegistry`    | Tabs on entity workspace pages                   | `WorkspaceTab(key, label, permission?, meta[])`                                                                                                                                                                                |
| `SettingsRegistry`     | Settings pages + setting definitions             | `SettingsPage(key, title, href, permission?, group?, routeName?, order?)` — `group` renders under a nav section; `routeName` is resolved lazily in `toArray()` (safe for plugin `boot()`); pages sort by `order` (default 500) |
| `SettingsManager`      | Settings storage (read/write by key)             | Injected class with `get(key)`, `set(key, value)`, `some(keys)` methods; persistence is supplied by the host through `SettingsStore`                                                                                             |
| `PermissionRegistry`   | Plugin-declared permissions                      | `register(key, label)` — persisted to Spatie permissions table via `platform:permissions:sync`                                                                                                                                 |
| `NotificationRegistry` | Notification drivers by name                     | `NotificationDriverRegistration(name, channel, driverClass, label, config[], laravelMailer?)`                                                                                                                                  |
| `PaymentRegistry`      | Payment gateways by key                          | class-string or instance of `PaymentGateway`                                                                                                                                                                                   |
| `OpenKOSManager`       | Central manager — entry point for all registries | Injected into plugins; facade exposes `OpenKOS::navigation()->...`, `OpenKOS::dashboard()->...`, etc.                                                                                                                           |

Conventions:

- Item objects are `final readonly` value objects with promoted constructors.
- Duplicate page keys silently overwrite — `SettingsRegistry::registerPage()` is idempotent so re-booting the provider (e.g. in tests) doesn't crash. Other registries throw on duplicate keys.
- `icon` is a **lucide icon name string** (PHP can't ship React components); the frontend resolves it to a component.
- `permission` is a Spatie permission string (e.g. `properties.view`), matching how the sidebar gates visibility today.

### Settings Storage (key-value via `Setting` model)

In addition to sidebar pages, `SettingsRegistry` now manages **setting definitions** that plugins register to persist key-value configuration without schema migrations:

```php
$platform->settings()->registerSetting(new SettingDefinition(
    key: 'my_plugin.api_key',
    label: 'API Key',
    type: 'encrypted',
    default: null,
    rules: ['nullable', 'string', 'max:255'],
    page: 'my-plugin',          // groups settings under a settings page key
));
```

Supported types: `string`, `boolean`, `integer`, `array`, `encrypted`, `encrypted:array`.

**Reading and writing** goes through `SettingsManager`, accessible via the manager. The package depends only on `OpenKOS\Core\Contracts\SettingsStore`; OpenKOS binds that contract to its Eloquent-backed settings service:

```php
// In a plugin's controller or Boot method:
$manager = $platform->settingsManager();

$value = $manager->get('my_plugin.api_key');
$manager->set('my_plugin.api_key', 'new-value', auth()->user());
```

Alternatively, app code can inject `SettingsManager` or use the facade:

```php
OpenKOS::settingsManager()->get('my_plugin.api_key');
```

**Validation** is defined per key on the `SettingDefinition` and enforced at write time by the manager via Laravel's `Validator`. A plugin's controller can also use its own FormRequest for HTTP-level validation.

**Automated UI**: Registering settings with a `page` key enables a generic settings page at `/settings/{page}`. The `DynamicSettingsForm` React component renders form fields from the registered definitions. Plugins can either use this generic endpoint or build custom pages.

**Storage**: All settings (core and plugin) live in a single key-value `settings` table. The `Setting` model is a thin facade — `get(key)`, `set(key, value)`, and `some(keys)` delegate to `App\Services\Settings\SettingManager`. `SettingCaster` handles serialize/deserialize. `SettingRegistry` wraps `config('settings')` for default values and casts. Core controllers go through `UpdateSettings` action (which records audit logs and dispatches `SettingsUpdated`). Plugin settings go through `SettingsManager` (which validates against registered `SettingDefinition` rules).

Defaults live in `config/settings.php` and define the core application settings:

```php
// config/settings.php
return [
    'site_name' => ['default' => 'OpenKOS', 'cast' => 'string'],
    'reminder_enabled' => ['default' => true, 'cast' => 'boolean'],
    'mail_config' => ['default' => [], 'cast' => 'encrypted:array'],
];
```

Plugins register their own settings via the platform registry instead: `$platform->settings()->registerSetting(new SettingDefinition(...))` — the core never needs to know about plugin settings. `Setting::get('key')` returns the DB value when present, or falls back to the registered default.

**Audit trail**: Settings updates are recorded to `audit_logs` via `UpdateSettings`. Every change also dispatches `App\Events\Settings\SettingsUpdated`, which `RecordActivitySubscriber` writes to `activity_logs`.

### Workspaces

`WorkspaceRegistry::for('property')` returns a memoized `Workspace` — a registrar scoped to one entity type. The manager exposes sugar for the aggregate roots: `property()`, `lease()`, `tenant()`; anything else goes through `workspace($name)` (e.g. `'unit'`). `WorkspaceTab.key` maps to the frontend `PluginRegion` slot `workspace-tab-{key}` in `entity-workspace-layout.tsx`.

## Manager & Facade

`OpenKOSManager` constructor-injects the six registries and is itself a singleton. Plugins receive it; application code uses the facade:

```php
use OpenKOS\Platform\Facades\OpenKOS;

OpenKOS::dashboard()->registerPage(...);
OpenKOS::navigation()->registerItem(...);
OpenKOS::property()->registerTab(...);       // = OpenKOS::workspace('property')->...
OpenKOS::settings()->registerPage(...);
OpenKOS::notifications()->registerDriver(...);
OpenKOS::payments()->registerGateway(...);
```

> **Gotcha:** there is deliberately **no global `OpenKOS` alias** — a root-level alias would collide with the `OpenKOS\` namespace. Always `use OpenKOS\Platform\Facades\OpenKOS;`.

## Writing a Plugin

A plugin is a class extending `Plugin` that declares a **manifest** and registers
extensions. The `src/Plugins/Example/` plugin is a working reference for everything below.

```php
use OpenKOS\Core\Events\PaymentRecorded;
use OpenKOS\Platform\Navigation\NavigationItem;
use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\Settings\SettingsPage;
use OpenKOS\Platform\Plugin\Plugin;
use OpenKOS\Platform\Plugin\PluginManifest;

class MyPlugin extends Plugin
{
    // Identity + compatibility. Required.
    public function manifest(): PluginManifest
    {
        return new PluginManifest(
            id: 'acme/my-plugin',      // unique, vendor-namespaced
            name: 'My Plugin',
            version: '1.0.0',
            coreVersion: '^0.2',        // constraint against config('platform.version')
            dependencies: [],           // ids of plugins that must load first
        );
    }

    // Register extensions on the platform registries. Runs in dependency order.
    public function register(OpenKOSManager $platform): void
    {
        // Declare the permission this plugin introduces...
        $platform->permissions()->register('my-feature.view', 'View My Feature');

        // ...then gate a nav item on it.
        $platform->navigation()->registerItem(new NavigationItem(
            title: 'My Feature',
            href: '/my-feature',
            icon: 'sparkles',
            permission: 'my-feature.view',   // Spatie permission gates visibility
        ));
    }

    // Optional — runs after ALL plugins have registered.
    // Register settings pages here (route() is available), not in register().
    public function boot(OpenKOSManager $platform): void
    {
        $platform->settings()->registerPage(new SettingsPage(
            key: 'my-plugin',
            title: 'My Plugin',
            href: '/settings/my-plugin',
            group: 'Credentials',
            order: 350,                          // inserts between Mail (300) and WhatsApp (400)
            routeName: 'settings.my-plugin.edit', // resolved lazily in toArray()
        ));
    }

    // Optional — subscribe to core domain events.
    public function listens(): array
    {
        return [PaymentRecorded::class => MyListener::class];
    }
}
```

Then list it in `config/platform.php` (explicit entries are processed first; dependencies decide the final lifecycle order):

```php
'plugins' => [
    MyPlugin::class,
],
```

**Routes and migrations** load by convention — drop them in the plugin's own directory
and they're picked up when the plugin is enabled, with no registration boilerplate:

```
src/Plugins/MyPlugin/
├── MyPlugin.php
├── routes/web.php                 # loaded via loadRoutesFrom()
└── database/migrations/           # loaded via loadMigrationsFrom()
```

### Lifecycle

The package `PlatformServiceProvider::boot()`:

1. Merges explicit plugin classes with the host's `PluginDiscovery` result and
   removes duplicate class names.
2. Resolves and validates **every** class before loading any plugin resources or
   running lifecycle methods. Each class must extend `Plugin`.
3. **Validates & orders** them with `PluginLoader`: checks each `coreVersion`
   against `config('platform.version')`, verifies declared `dependencies` exist,
   and topologically sorts so each plugin loads after its dependencies. Throws on
   an incompatible version, missing dependency, dependency cycle, or duplicate id.
4. **Loads resources** — each plugin's `routes/web.php` and `database/migrations/`.
5. Runs **two passes**: every plugin's `register()`, then every plugin's `boot()`
   (so `boot()` can rely on all plugins having registered).
6. **Wires event listeners** from each plugin's `listens()` onto Laravel's dispatcher.

### Manifest, versioning & dependencies

- **Manifest** (`PluginManifest`): `id`, `name`, `version`, `description`,
  `coreVersion`, `dependencies`. It's a PHP value object, not a JSON file — type-safe
  and IDE-navigable; a JSON manifest can wrap it later if external discovery needs one.
- **Version compatibility**: `coreVersion` is checked against `config('platform.version')`
  (currently `0.2.0`). Supported constraints: any Composer semver constraint supported by `composer/semver`
  (`*`, `^`, `~`, ranges, wildcards like `1.*`, `||`, …). Incompatible plugins fail fast at boot rather than
  half-loading.
- **Dependencies**: a plugin lists other plugin **ids**; the loader guarantees they're
  present and loaded first. Missing deps and cycles are hard errors.

### Domain events

Core dispatches package domain events (e.g. `OpenKOS\Core\Events\PaymentRecorded`); plugins subscribe
declaratively via `listens()` (`event => listener` / `[listeners]`), wired onto Laravel's
event dispatcher at boot. This is the standard extension seam for reacting to core
activity (accounting, analytics, notifications) **without modifying core** — the action
just dispatches; any number of plugins can listen. Application workflows may also dispatch
legacy `App\Events` for host-only subscribers.

### Discovery

The standalone package exposes `OpenKOS\Core\Contracts\PluginDiscovery`, but does not
scan Composer itself. OpenKOS provides the host implementation, which enumerates installed
packages and accepts exactly one plugin class per package through this metadata:

```json
{
    "extra": {
        "openkos": {
            "plugin": "Vendor\\Package\\MyPlugin"
        }
    }
}
```

The host merges explicit classes first, then discovered classes, and removes duplicates.
`PluginLoader` still controls dependency order. `platform.discovery.disabled_packages`
accepts Composer package names for an explicit opt-out. Discovery is disabled by default
in the standalone package and enabled by the OpenKOS application.

Installing a Composer plugin grants trusted in-process PHP execution; disabled packages
are not sandboxed. Frontend assets remain out of scope and must still be handled by the host.

### Runtime ZIP plugins

OpenKOS also accepts prepared runtime plugin artifacts without changing the root
`composer.json`, `composer.lock`, or `vendor/` directory. Runtime packages live in the
persistent `storage/app/private/plugins` directory by default and are discovered only
when enabled in the separate `state.json` file.

An artifact must contain `manifest.json`, `composer.json`, `composer.lock`, `src/`, and
a bundled `vendor/autoload.php`. The runtime entrypoint must be an
`OpenKOS\\Platform\\Plugin\\Plugin` subclass. The manifest's `id`, `version`, entry class,
core constraint, PHP constraint, and plugin dependencies must match the entry class and
Composer metadata.

Runtime installation is intentionally a strict plugin contract. Laravel service
providers are not runtime entrypoints; a package such as Fonnte must expose an OpenKOS
`Plugin` class and may delegate internally as needed.

The installer validates each artifact in a fresh PHP process before promotion, so an
already-loaded class from an older version cannot be mistaken for the staged version.
Archive entry names are normalized and bounded, and bundled dependency versions must
satisfy the artifact's Composer constraints.

Use the operator-only CLI lifecycle:

```text
php artisan plugin:install /path/to/plugin.zip
php artisan plugin:list
php artisan plugin:enable openkos/example
php artisan plugin:disable openkos/example
php artisan plugin:remove openkos/example --force
```

Installation and state changes are durable immediately but take effect after a fresh
application process starts. Restart FrankenPHP workers, queue workers, and the scheduler
after changing runtime plugins. If a plugin ships migrations or permissions, run
`php artisan migrate` and `php artisan platform:permissions:sync` separately.

Runtime and Composer copies of the same plugin ID or entry class are conflicts; neither
copy is loaded. Runtime artifacts are trusted in-process code, so the installer rejects
unsafe archive paths, symlinks, executable entries, install scripts, incompatible host
dependencies, and oversized archives before activation. Host-provided packages such as
Laravel, Illuminate, Composer Semver, and the OpenKOS platform must not be bundled.

### Security & permission boundaries

Plugins run **in-process with full application access** — there is no sandbox. The trust
boundary is therefore _installation_: only enable plugins you trust, exactly like any
Composer dependency. What the platform **does** enforce:

- **Permissions** — a plugin declares its own permissions via
  `$platform->permissions()->register('my-feature.view', 'label')`;
  `php artisan platform:permissions:sync` persists them into the Spatie permissions
  table (run it after enabling a plugin, alongside `migrate`). Plugins never edit core
  seeders.
- **UI/authorization gating** — every registry item carries an optional `permission`,
  so a plugin's nav item, dashboard page, settings page, or workspace tab is only shown
  to users who hold it. Plugin routes apply the same `permission:` middleware (see the
  example plugin's `routes/web.php`).
- **No schema collisions** — plugins own their tables via their own migrations; they must
  not alter core tables. Core schema and business logic stay untouched.
- **Fail-fast loading** — version/dependency validation stops an incompatible or broken
  plugin from partially booting.

Sandboxing (capability limits, resource isolation) is explicitly out of scope for the
monolith; it would only matter for untrusted third-party marketplace plugins.

## Notifications (WhatsApp) — consumed

`NotificationRegistry` is now the **runtime source of truth** for WhatsApp drivers, replacing the old `config('services.whatsapp.drivers')` lookups:

- **`WhatsAppPlugin`** (`src/Plugins/WhatsApp/`, enabled in `config/platform.php`) seeds the registry from `config/services.php` at boot — each entry becomes a `NotificationDriverRegistration(name, channel: 'whatsapp', driverClass, label, config)`. Config is now just seed data; a third-party plugin can register additional whatsapp-channel drivers the same way and they appear everywhere automatically.
- **`WhatsAppManager`** resolves the selected driver from the registry (`$registry->get($name)`) instead of config, then instantiates `driverClass` with merged credentials (DB `whatsapp_config` over registration defaults). The package `OpenKOS\Core\Contracts\WhatsAppDriver` is implemented by the bundled application drivers and standalone integration packages such as `openkos/whatsapp-fonnte`.
- **The WhatsApp settings page** (`WhatsAppController`) lists drivers via `$registry->forChannel('whatsapp')` and validates the selection against it.

`NotificationDriverRegistration.driverClass` is a plain class-string, not a typed contract, because each channel brings its own driver interface shaped to its needs — WhatsApp drivers implement the stateful `OpenKOS\Core\Contracts\WhatsAppDriver` (pairing/health). A future SMS/Telegram/push channel follows the same pattern: define a channel-specific driver contract in the package, register implementations into `NotificationRegistry` with that `channel`, and add a small app-side manager that resolves and calls them. The registry, registration, and settings-page listing are channel-agnostic and reused as-is; only the per-channel contract and manager are new.

### Mail drivers

Mail drivers may optionally advertise the Laravel mailer they support:

    $platform->notifications()->registerDriver(new NotificationDriverRegistration(
        name: 'third-party/acme-mail',
        channel: 'mail',
        driverClass: AcmeMailDriver::class,
        label: 'Acme Mail',
        laravelMailer: 'acme-mail',
    ));

The plugin owns registration of the actual Laravel mailer and transport. Omitting `laravelMailer` keeps the driver valid for OpenKOS custom notifications through `MailManager`, but native Laravel notifications use the Laravel `log` fallback with an explicit warning. The platform does not contain provider-specific mappings.

## Payment Contracts

`PaymentGateway` is the typed, provider-independent contract for creating payments and normalizing webhook results. `PaymentRegistry` resolves gateways by key. Gateway-specific attempt persistence belongs to the app: `PaymentAttempt` records the local invoice checkout lifecycle, while the existing canonical `Payment` remains the only source used for invoice accounting.

## How Registrations Reach the UI

1. **Shared prop** — `HandleInertiaRequests::share()` exposes `platform` (`navigation`, `workspaces`, `settings`, `dashboard` serialized via `toArray()`). Typed in `resources/js/types/platform.ts`.
2. **Conversion helpers** — `resources/js/lib/platform.ts`: `canSee()` (owner bypass or permission match), `platformNavItems()` (nav trees, resolves icon names via an explicit lucide map with a fallback), `platformPageNavItems()` (settings pages), `usePlatformTabs(workspace)` (permission-filtered tabs from the shared prop).
3. **Consumers** — `app-sidebar.tsx` appends `platform.navigation.main`/`.footer` after the hardcoded items and `platform.dashboard` pages as children of the Dashboard group; `layouts/settings/layout.tsx` appends `platform.settings`; every entity workspace layout renders its tab strip through the shared `components/shared/workspace-tabs.tsx`, which appends platform tabs.
4. **Workspace tabs are URL-routed.** Every workspace (`property`, `tenant`, `lease`, `unit`, `maintenance-ticket`, `user`, `role`) uses route-per-tab navigation — clicking a tab navigates (e.g. `/tenants/5/leases`). A platform tab therefore **must** provide `meta: ['href' => ...]`; `{id}` is replaced with the entity id client-side (`{propertyId}` is also available on `unit`). Tabs without `meta.href` are skipped. A real plugin registers its own route + Inertia page and points the tab's href at it.
5. **PluginRegion** — named extension slots (`workspace-header-badge`, `workspace-tabs-before/after`, `workspace-tab-{key}` around built-in tab content, etc.). In-repo plugin frontend code lives in `resources/js/plugins/{name}/` and calls `registerRegion(name, Component)` (loaded via a side-effect import in `app.tsx`).

### ExamplePlugin (disabled by default)

`ExamplePlugin` (`src/Plugins/Example/`) is a working reference for **every** extension point: a manifest, a declared permission (`example.view`) gating its nav item and route, the consumed registries (sidebar nav item, Dashboard sub-page, settings nav entry, plus a `workspace-header-badge` region — client half in `resources/js/plugins/example/`), a domain-event listener (`Listeners/LogPaymentRecorded` on `OpenKOS\Core\Events\PaymentRecorded`), its own `routes/web.php` (an invokable-controller `/example` endpoint), and a `database/migrations/` migration.

It ships **disabled** so the demo stays out of the real UI. To enable it:

1. Uncomment `// ExamplePlugin::class,` and its `use` import in `config/platform.php` — activates the manifest, registry registrations, event listener, and route/migration loading.
2. Uncomment `// import './example';` in `resources/js/plugins/index.ts` — the client-side header badge.
3. Run `php artisan migrate` to create the plugin's `example_widgets` table, and
   `php artisan platform:permissions:sync` to create its `example.view` permission.

The backend and client halves are independent: registry entries + route + listener come from (1), the header badge from (2).

### Future work

1. Move built-in nav/tabs into a core plugin so built-ins and plugins go through one path (only if the dual path starts to hurt).
2. A story for shipping plugin frontend code (today it must live in-repo under `resources/js/plugins/`).
3. `PaymentGateway`/`PaymentRegistry` wiring once a real gateway (Midtrans/Xendit) is chosen.

## Testing

- `tests/Unit/Platform/` — registries (framework-free) and `PluginLoaderTest` (version/dependency/ordering logic).
- `tests/Feature/Platform/` — container/facade resolution, workspace scoping, and the plugin boot lifecycle: register-before-boot ordering, event-listener wiring (`PluginEventTest`), and convention-based route/migration loading (`PluginResourcesTest`).

Note: platform registries are container singletons; tests that enable a plugin assert _contains_, not exact counts.
