# Platform Layer & Plugin System

> **Status:** Foundation shipped and consumed (v0.1 Alpha) — registrations render in the sidebar, settings nav, and workspace tabs; plugins carry manifests, subscribe to domain events, and ship their own routes/migrations
> **Purpose:** The plugin extension strategy — registries, the OpenKOS manager/facade, the plugin lifecycle, manifest/versioning/dependency rules, domain events, and extension boundaries. For general layer conventions see `docs/architecture.md`.

## Why

OpenKOS is becoming an extensible platform. Plugins must be able to add navigation items, dashboard pages, workspace tabs, settings pages, notification drivers, and payment gateways — without forking the app. This layer is the seam that makes that possible. It was introduced additively: no schema changes, no UI changes, no domain rewrites.

## Namespace & Location

Platform code lives in `src/` under the `OpenKOS\` namespace (PSR-4 mapped in `composer.json`), physically separate from the application code in `app/`:

```
src/
├── Core/
│   └── Contracts/            Platform-facing interfaces (PaymentGateway, PluginDiscovery)
├── Platform/
│   ├── OpenKOSManager.php    Central manager — the object plugins receive
│   ├── PlatformServiceProvider.php
│   ├── Facades/OpenKOS.php
│   ├── Plugin/               Plugin base, PluginManifest, PluginLoader
│   ├── Dashboard/            DashboardRegistry + DashboardPage
│   ├── Navigation/           NavigationRegistry + NavigationItem
│   ├── Workspace/            WorkspaceRegistry + Workspace + WorkspaceTab
│   ├── Settings/             SettingsRegistry + SettingsPage
│   ├── Notification/         NotificationRegistry
│   └── Payment/              PaymentRegistry
└── Plugins/
    ├── WhatsApp/             Core plugin: registers built-in WhatsApp drivers
    └── Example/              Reference plugin (manifest, listener, routes/,
                              database/migrations/) — disabled by default (see below)
```

Core also dispatches domain events plugins can subscribe to, e.g. `App\Events\Payment\PaymentRecorded`.

`src/Core/` currently holds only contracts. It is the designated future home for domain code migrating out of `app/` — nothing has been moved yet, deliberately.

## The Registries

Six registries, each bound as a **container singleton** (no static state) in `PlatformServiceProvider::register()`. All implement `Arrayable`, so exposing any of them to the frontend later is `->toArray()` in a shared Inertia prop.

| Registry               | Registers                                        | Item shape                                                                                                                                                                                                                     |
| ---------------------- | ------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `NavigationRegistry`   | Sidebar nav items, grouped (`main`, `footer`, …) | `NavigationItem(title, href?, icon?, permission?, children[])` — mirrors the TS `NavItem` type                                                                                                                                 |
| `DashboardRegistry`    | Dashboard pages                                  | `DashboardPage(key, title, href, permission?)`                                                                                                                                                                                 |
| `WorkspaceRegistry`    | Tabs on entity workspace pages                   | `WorkspaceTab(key, label, permission?, meta[])`                                                                                                                                                                                |
| `SettingsRegistry`     | Settings pages                                   | `SettingsPage(key, title, href, permission?, group?, routeName?, order?)` — `group` renders under a nav section; `routeName` is resolved lazily in `toArray()` (safe for plugin `boot()`); pages sort by `order` (default 500) |
| `NotificationRegistry` | Notification drivers by name                     | `NotificationDriverRegistration(name, channel, driverClass, label, config[])`                                                                                                                                                  |
| `PaymentRegistry`      | Payment gateways by key                          | class-string or instance of `PaymentGateway`                                                                                                                                                                                   |

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

**Reading and writing** goes through `SettingsManager`, accessible via the manager:

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
use App\Events\Payment\PaymentRecorded;
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
            coreVersion: '^0.1',        // constraint against config('platform.version')
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

Then list it in `config/platform.php` (order doesn't matter — dependencies decide it):

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

`PlatformServiceProvider::boot()`:

1. Instantiates every configured plugin through the container (so plugins can
   constructor-inject dependencies).
2. **Validates & orders** them with `PluginLoader`: checks each `coreVersion`
   against `config('platform.version')`, verifies declared `dependencies` exist,
   and topologically sorts so each plugin loads after its dependencies. Throws on
   an incompatible version, missing dependency, dependency cycle, or duplicate id.
3. **Loads resources** — each plugin's `routes/web.php` and `database/migrations/`.
4. Runs **two passes**: every plugin's `register()`, then every plugin's `boot()`
   (so `boot()` can rely on all plugins having registered).
5. **Wires event listeners** from each plugin's `listens()` onto Laravel's dispatcher.

### Manifest, versioning & dependencies

- **Manifest** (`PluginManifest`): `id`, `name`, `version`, `description`,
  `coreVersion`, `dependencies`. It's a PHP value object, not a JSON file — type-safe
  and IDE-navigable; a JSON manifest can wrap it later if external discovery needs one.
- **Version compatibility**: `coreVersion` is checked against `config('platform.version')`
  (currently `0.1.0`). Supported constraints: any Composer semver constraint supported by `composer/semver`
  (`*`, `^`, `~`, ranges, wildcards like `1.*`, `||`, …). Incompatible plugins fail fast at boot rather than
  half-loading.
- **Dependencies**: a plugin lists other plugin **ids**; the loader guarantees they're
  present and loaded first. Missing deps and cycles are hard errors.

### Domain events

Core dispatches domain events (e.g. `App\Events\Payment\PaymentRecorded`); plugins subscribe
declaratively via `listens()` (`event => listener` / `[listeners]`), wired onto Laravel's
event dispatcher at boot. This is the standard extension seam for reacting to core
activity (accounting, analytics, notifications) **without modifying core** — the action
just dispatches; any number of plugins can listen. Add new domain events as core
operations warrant; keep them in `App\Events` as part of the stable plugin API.

### Discovery

Plugins are listed explicitly in `config/platform.php`. `OpenKOS\Core\Contracts\PluginDiscovery`
(`discover(): array` of plugin class-strings) is the seam for future Composer-package
discovery — interface only, no implementation. Note that shipping a plugin's **frontend**
code (React pages/regions) still requires it to live in-repo under `resources/js/plugins/`;
a bundling story (build-time registration or module federation) is the open problem
that gates true external plugins.

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
monolith; it would only matter for untrusted third-party marketplace plugins, which is a
future concern.

## Notifications (WhatsApp) — consumed

`NotificationRegistry` is now the **runtime source of truth** for WhatsApp drivers, replacing the old `config('services.whatsapp.drivers')` lookups:

- **`WhatsAppPlugin`** (`src/Plugins/WhatsApp/`, enabled in `config/platform.php`) seeds the registry from `config/services.php` at boot — each entry becomes a `NotificationDriverRegistration(name, channel: 'whatsapp', driverClass, label, config)`. Config is now just seed data; a third-party plugin can register additional whatsapp-channel drivers the same way and they appear everywhere automatically.
- **`WhatsAppManager`** resolves the selected driver from the registry (`$registry->get($name)`) instead of config, then instantiates `driverClass` with merged credentials (DB `whatsapp_config` over registration defaults). The existing `App\Contracts\WhatsAppDriver` interface and the four driver classes are **unchanged**.
- **The WhatsApp settings page** (`WhatsAppController`) lists drivers via `$registry->forChannel('whatsapp')` and validates the selection against it.

`NotificationDriverRegistration.driverClass` is a plain class-string, not a typed contract, because each channel brings its own driver interface shaped to its needs — WhatsApp drivers implement the stateful `App\Contracts\WhatsAppDriver` (pairing/health). A future SMS/Telegram/push channel follows the same pattern: define a channel-specific driver contract (e.g. `App\Contracts\SmsDriver`), register implementations into `NotificationRegistry` with that `channel`, and add a small manager (parallel to `WhatsAppManager`) that resolves and calls them. The registry, registration, and settings-page listing are channel-agnostic and reused as-is; only the per-channel contract and manager are new.

## Payment Contracts — interface only

`PaymentGateway` (`key()`, `displayName()`, `createPayment(array): array`, `handleCallback(array): array`, `configurationSchema()`) and `PaymentRegistry` exist but have no implementations. Deliberately dormant until a real gateway (Midtrans/Xendit) forces the payload shape — the first integration should define the interface, not a guess.

## How Registrations Reach the UI

1. **Shared prop** — `HandleInertiaRequests::share()` exposes `platform` (`navigation`, `workspaces`, `settings`, `dashboard` serialized via `toArray()`). Typed in `resources/js/types/platform.ts`.
2. **Conversion helpers** — `resources/js/lib/platform.ts`: `canSee()` (owner bypass or permission match), `platformNavItems()` (nav trees, resolves icon names via an explicit lucide map with a fallback), `platformPageNavItems()` (settings pages), `usePlatformTabs(workspace)` (permission-filtered tabs from the shared prop).
3. **Consumers** — `app-sidebar.tsx` appends `platform.navigation.main`/`.footer` after the hardcoded items and `platform.dashboard` pages as children of the Dashboard group; `layouts/settings/layout.tsx` appends `platform.settings`; every entity workspace layout renders its tab strip through the shared `components/shared/workspace-tabs.tsx`, which appends platform tabs.
4. **Workspace tabs are URL-routed.** Every workspace (`property`, `tenant`, `lease`, `unit`, `maintenance-ticket`, `user`, `role`) uses route-per-tab navigation — clicking a tab navigates (e.g. `/tenants/5/leases`). A platform tab therefore **must** provide `meta: ['href' => ...]`; `{id}` is replaced with the entity id client-side (`{propertyId}` is also available on `unit`). Tabs without `meta.href` are skipped. A real plugin registers its own route + Inertia page and points the tab's href at it.
5. **PluginRegion** — named extension slots (`workspace-header-badge`, `workspace-tabs-before/after`, `workspace-tab-{key}` around built-in tab content, etc.). In-repo plugin frontend code lives in `resources/js/plugins/{name}/` and calls `registerRegion(name, Component)` (loaded via a side-effect import in `app.tsx`).

### ExamplePlugin (disabled by default)

`ExamplePlugin` (`src/Plugins/Example/`) is a working reference for **every** extension point: a manifest, a declared permission (`example.view`) gating its nav item and route, the consumed registries (sidebar nav item, Dashboard sub-page, settings nav entry, plus a `workspace-header-badge` region — client half in `resources/js/plugins/example/`), a domain-event listener (`Listeners/LogPaymentRecorded` on `App\Events\Payment\PaymentRecorded`), its own `routes/web.php` (an invokable-controller `/example` endpoint), and a `database/migrations/` migration.

It ships **disabled** so the demo stays out of the real UI. To enable it:

1. Uncomment `// ExamplePlugin::class,` and its `use` import in `config/platform.php` — activates the manifest, registry registrations, event listener, and route/migration loading.
2. Uncomment `// import './example';` in `resources/js/plugins/index.ts` — the client-side header badge.
3. Run `php artisan migrate` to create the plugin's `example_widgets` table, and
   `php artisan platform:permissions:sync` to create its `example.view` permission.

The backend and client halves are independent: registry entries + route + listener come from (1), the header badge from (2).

### Future work

1. Move built-in nav/tabs into a core plugin so built-ins and plugins go through one path (only if the dual path starts to hurt).
2. Composer-based `PluginDiscovery` implementation, including a story for shipping plugin frontend code (today it must live in-repo under `resources/js/plugins/`).
3. `PaymentGateway`/`PaymentRegistry` wiring once a real gateway (Midtrans/Xendit) is chosen.

## Testing

- `tests/Unit/Platform/` — registries (framework-free) and `PluginLoaderTest` (version/dependency/ordering logic).
- `tests/Feature/Platform/` — container/facade resolution, workspace scoping, and the plugin boot lifecycle: register-before-boot ordering, event-listener wiring (`PluginEventTest`), and convention-based route/migration loading (`PluginResourcesTest`).

Note: platform registries are container singletons; tests that enable a plugin assert _contains_, not exact counts.
