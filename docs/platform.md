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
│   └── Contracts/            Platform-facing interfaces (NotificationDriver, PaymentGateway, PluginDiscovery)
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
    └── Example/              Reference plugin (enabled in config/platform.php)
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
| `NotificationRegistry` | Notification drivers by name | class-string or instance of `NotificationDriver` |
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

## Notification & Payment Contracts

Interfaces only — no implementations exist yet:

- `NotificationDriver` — `name()`, `channel()` (e.g. `'whatsapp'`), `send(recipient, message, options)`, `configurationSchema()`. Deliberately mirrors the conventions of the existing `App\Contracts\WhatsAppDriver`, which is **untouched**; a future adapter can wrap the existing WhatsApp drivers (Meta, Fonnte, Baileys) into platform plugins.
- `PaymentGateway` — `key()`, `displayName()`, `createPayment(array): array`, `handleCallback(array): array`, `configurationSchema()`. Loose array payloads until a real gateway forces the shape.

Both registries accept class-strings or instances; resolution is deferred until real drivers exist.

## How Registrations Reach the UI

1. **Shared prop** — `HandleInertiaRequests::share()` exposes `platform` (`navigation`, `workspaces`, `settings`, `dashboard` serialized via `toArray()`). Typed in `resources/js/types/platform.ts`.
2. **Conversion helpers** — `resources/js/lib/platform.ts`: `canSee()` (owner bypass or permission match), `platformNavItems()` (nav trees, resolves icon names via an explicit lucide map with a fallback), `platformPageNavItems()` (settings pages), `usePlatformTabs(workspace)` (permission-filtered tabs from the shared prop).
3. **Consumers** — `app-sidebar.tsx` appends `platform.navigation.main`/`.footer` after the hardcoded items; `layouts/settings/layout.tsx` appends `platform.settings`; the workspace show pages (`leases/show.tsx`, `tenants/show.tsx`, `properties/rooms/show.tsx`) append platform tabs to their local `TABS`.
4. **Tab content** — a platform tab on a stateful workspace renders `<PluginRegion name="workspace-tab-{key}" />`. `PluginRegion` looks up a module-level client registry; in-repo plugin frontend code lives in `resources/js/plugins/{name}/` and calls `registerRegion(name, Component)` (loaded via a side-effect import in `app.tsx`).
5. **Property workspace exception** — property tabs are URL-routed, so a platform tab there needs `meta: ['href' => '/properties/{id}/...']`; `{id}` is replaced with the property id client-side. Tabs without `meta.href` are skipped on property pages.

`ExamplePlugin` demonstrates all of it (gated by `OPENKOS_EXAMPLE_PLUGIN`, default on — set `false` in production): a sidebar nav item, a settings nav entry, a lease workspace tab whose body comes from `resources/js/plugins/example/`, and a property tab via `meta.href`.

### Future work

1. Move built-in nav/tabs into a core plugin so built-ins and plugins go through one path (only if the dual path starts to hurt).
2. Adapter to expose existing WhatsApp drivers through `NotificationRegistry`.
3. Composer-based `PluginDiscovery` implementation, including a story for shipping plugin frontend code (today it must live in-repo under `resources/js/plugins/`).

## Testing

- `tests/Unit/Platform/` — one file per registry; plain unit tests (registries are framework-free classes).
- `tests/Feature/Platform/` — container/facade singleton resolution, workspace scoping, and the plugin boot lifecycle (register-before-boot ordering, config-driven loading).

Note: registries are singletons and `ExamplePlugin` registers by default — tests assert *contains*, never exact counts.
