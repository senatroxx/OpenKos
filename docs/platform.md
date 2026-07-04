# Platform Layer & Plugin System

> **Status:** Foundation shipped and consumed (v0.1 Alpha) — registrations render in the sidebar, settings nav, and workspace tabs
> **Purpose:** Describe the extensibility architecture: registries, the OpenKOS manager/facade, and the plugin lifecycle. For general layer conventions see `docs/architecture.md`.

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
│   ├── Plugin/Plugin.php     Abstract base class for plugins
│   ├── Dashboard/            DashboardRegistry + DashboardPage
│   ├── Navigation/           NavigationRegistry + NavigationItem
│   ├── Workspace/            WorkspaceRegistry + Workspace + WorkspaceTab
│   ├── Settings/             SettingsRegistry + SettingsPage
│   ├── Notification/         NotificationRegistry
│   └── Payment/              PaymentRegistry
└── Plugins/
    ├── WhatsApp/             Core plugin: registers built-in WhatsApp drivers
    └── Example/              Reference plugin — disabled by default (see below)
```

`src/Core/` currently holds only contracts. It is the designated future home for domain code migrating out of `app/` — nothing has been moved yet, deliberately.

## The Registries

Six registries, each bound as a **container singleton** (no static state) in `PlatformServiceProvider::register()`. All implement `Arrayable`, so exposing any of them to the frontend later is `->toArray()` in a shared Inertia prop.

| Registry | Registers | Item shape |
|---|---|---|
| `NavigationRegistry` | Sidebar nav items, grouped (`main`, `footer`, …) | `NavigationItem(title, href?, icon?, permission?, children[])` — mirrors the TS `NavItem` type |
| `DashboardRegistry` | Dashboard pages | `DashboardPage(key, title, href, permission?)` |
| `WorkspaceRegistry` | Tabs on entity workspace pages | `WorkspaceTab(key, label, permission?, meta[])` |
| `SettingsRegistry` | Settings pages | `SettingsPage(key, title, href, permission?)` |
| `NotificationRegistry` | Notification drivers by name | `NotificationDriverRegistration(name, channel, driverClass, label, config[])` |
| `PaymentRegistry` | Payment gateways by key | class-string or instance of `PaymentGateway` |

Conventions:

- Item objects are `final readonly` value objects with promoted constructors.
- Duplicate keys throw `InvalidArgumentException` — plugins fail loudly at boot, not silently overwrite each other.
- `icon` is a **lucide icon name string** (PHP can't ship React components); the frontend resolves it to a component.
- `permission` is a Spatie permission string (e.g. `properties.view`), matching how the sidebar gates visibility today.

### Workspaces

`WorkspaceRegistry::for('property')` returns a memoized `Workspace` — a registrar scoped to one entity type. The manager exposes sugar for the aggregate roots: `property()`, `lease()`, `tenant()`; anything else goes through `workspace($name)` (e.g. `'room'`). `WorkspaceTab.key` maps to the frontend `PluginRegion` slot `workspace-tab-{key}` in `entity-workspace-layout.tsx`.

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

```php
use OpenKOS\Platform\Navigation\NavigationItem;
use OpenKOS\Platform\OpenKOSManager;
use OpenKOS\Platform\Plugin\Plugin;

class MyPlugin extends Plugin
{
    public function register(OpenKOSManager $platform): void
    {
        $platform->navigation()->registerItem(new NavigationItem(
            title: 'My Feature',
            href: '/my-feature',
            icon: 'sparkles',
            permission: 'my-feature.view',
        ));
    }

    // Optional — runs after ALL plugins have registered.
    public function boot(OpenKOSManager $platform): void {}
}
```

Then list it in `config/platform.php`:

```php
'plugins' => [
    MyPlugin::class,
],
```

### Lifecycle

`PlatformServiceProvider::boot()` instantiates every configured plugin through the container (so plugins can constructor-inject dependencies), then runs **two passes**: every plugin's `register()` first, then every plugin's `boot()`. A plugin's `boot()` can therefore rely on all other plugins having registered.

For now plugins are listed explicitly in config. `OpenKOS\Core\Contracts\PluginDiscovery` (`discover(): array` of plugin class-strings) is the seam for future Composer-based discovery — interface only, no implementation.

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
4. **Workspace tabs are URL-routed.** Every workspace (`property`, `tenant`, `lease`, `room`, `maintenance-ticket`, `user`, `role`) uses route-per-tab navigation — clicking a tab navigates (e.g. `/tenants/5/leases`). A platform tab therefore **must** provide `meta: ['href' => ...]`; `{id}` is replaced with the entity id client-side (`{propertyId}` is also available on `room`). Tabs without `meta.href` are skipped. A real plugin registers its own route + Inertia page and points the tab's href at it.
5. **PluginRegion** — named extension slots (`workspace-header-badge`, `workspace-tabs-before/after`, `workspace-tab-{key}` around built-in tab content, etc.). In-repo plugin frontend code lives in `resources/js/plugins/{name}/` and calls `registerRegion(name, Component)` (loaded via a side-effect import in `app.tsx`).

### ExamplePlugin (disabled by default)

`ExamplePlugin` (`src/Plugins/Example/`) is a working reference that demonstrates every consumed registry — a sidebar nav item, a Dashboard sub-page, a settings nav entry, and a `workspace-header-badge` region component (client half in `resources/js/plugins/example/`). Its source also shows a commented workspace-tab registration.

It ships **disabled** so the demo stays out of the real UI. To enable it, uncomment all three spots:

1. `// ExamplePlugin::class,` and its `use` import in `config/platform.php` (backend registrations)
2. `// import './example';` in `resources/js/plugins/index.ts` (the client-side header badge)

The backend and client halves are independent — the nav/dashboard/settings entries come from (1), the header badge from (2).

### Future work

1. Move built-in nav/tabs into a core plugin so built-ins and plugins go through one path (only if the dual path starts to hurt).
2. Adapter to expose existing WhatsApp drivers through `NotificationRegistry`.
3. Composer-based `PluginDiscovery` implementation, including a story for shipping plugin frontend code (today it must live in-repo under `resources/js/plugins/`).

## Testing

- `tests/Unit/Platform/` — one file per registry; plain unit tests (registries are framework-free classes).
- `tests/Feature/Platform/` — container/facade singleton resolution, workspace scoping, and the plugin boot lifecycle (register-before-boot ordering, config-driven loading).

Note: registries are singletons and `ExamplePlugin` registers by default — tests assert *contains*, never exact counts.
