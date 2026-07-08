# Create standalone FonnteDriver Composer Package

Create a standalone Composer package at a new repository `openkos-fonnte` that provides a Fonnte WhatsApp driver for OpenKOS. This package is installed via Composer and enabled in `config/platform.php`.

## Repository Structure

```
openkos-fonnte/
├── composer.json
├── src/
│   ├── FonntePlugin.php
│   ├── Drivers/
│   │   └── FonnteDriver.php
│   ├── routes/
│   │   └── web.php
│   └── config/
│       └── settings.php
├── tests/
│   └── Feature/
│       └── FonnteDriverTest.php
└── README.md
```

## Package Details

### `composer.json`

- Name: `openkos/fonnte`
- Namespace: `OpenKOS\Fonnte\` mapped to `src/`
- Require: `openkos/core: ^0.1` (or whatever the core package is)
- Require-dev: `phpunit/phpunit: ^12`, `pestphp/pest: ^4`

### `src/FonntePlugin.php`

Extends `OpenKOS\Platform\Plugin\Plugin`:

- **Manifest:** id `openkos/fonnte`, name `Fonnte Driver`, version `1.0.0`, coreVersion `^0.1`
- **register():** calls `$platform->notifications()->registerDriver(...)` with channel `'whatsapp'`, driverClass `FonnteDriver::class`, label `'Fonnte (Unofficial)'`, config `['token' => env('FONNTE_TOKEN')]`
- **boot():** registers a settings page — `new SettingsPage(key: 'fonnte', title: 'Fonnte', href: '/settings/fonnte', group: 'Credentials', order: 410, routeName: 'settings.fonnte.edit')` (order 410 places it right after WhatsApp at 400)
- **Import:** `use OpenKOS\Platform\Notification\NotificationDriverRegistration;`, `use OpenKOS\Platform\Settings\SettingsPage;`

### `src/Drivers/FonnteDriver.php`

Copy from `app/Notifications/Drivers/FonnteDriver.php`, update namespace to `OpenKOS\Fonnte\Drivers`.

Implements `App\Contracts\WhatsAppDriver`:

```php
namespace OpenKOS\Fonnte\Drivers;

use App\Contracts\WhatsAppDriver;
use App\Data\WhatsApp\DriverHealthResult;
use App\Data\WhatsApp\WhatsAppMessage;
use Illuminate\Support\Facades\Http;
```

Methods: `send()`, `health()`, `supportsPairing()` (false), `configurationSchema()` (token field), `getPairingQrCode()` (null), `pair()` (throws), `disconnect()` (throws).

### `src/routes/web.php`

```php
Route::get('/settings/fonnte', fn () => inertia('settings/fonnte', [
    'config' => Setting::get('fonnte_config') ?? [],
]))->middleware(['auth', 'role:owner'])->name('settings.fonnte.edit');
```

Loads an Inertia page (shipped as in-repo frontend code in the consuming app, or via a simple Blade view if Inertia isn't available).

### `src/config/settings.php`

```php
return [
    'fonnte_config' => ['default' => [], 'cast' => 'encrypted:array'],
];
```

## Installation in the consuming app

1. `composer require openkos/fonnte`
2. Add to `config/platform.php`:
   ```php
   'plugins' => [
       OpenKOS\Fonnte\FonntePlugin::class,
   ],
   ```
3. Remove `'fonnte'` from `config/services.php` `whatsapp.drivers` (the plugin registers it now)
4. Run `php artisan optimize:clear`

## Contracts (provided by core, not duplicated)

| Contract | Namespace | Provided by |
|---|---|---|
| `WhatsAppDriver` | `App\Contracts` | openkos/core |
| `WhatsAppMessage` | `App\Data\WhatsApp` | openkos/core |
| `DriverHealthResult` | `App\Data\WhatsApp` | openkos/core |
| `Plugin` base | `OpenKOS\Platform\Plugin` | openkos/core |
| `PluginManifest` | `OpenKOS\Platform\Plugin` | openkos/core |
| `NotificationDriverRegistration` | `OpenKOS\Platform\Notification` | openkos/core |
| `SettingsPage` | `OpenKOS\Platform\Settings` | openkos/core |
| `OpenKOSManager` | `OpenKOS\Platform` | openkos/core |
| `NotificationRegistry` | `OpenKOS\Platform\Notification` | openkos/core |

## Tests

```
tests/Feature/FonnteDriverTest.php
```

Same tests as the existing `tests/Feature/Notifications/Drivers/FonnteDriverTest.php`.

## Frontend

There are two options — pick the one that fits the plugin's deployment model:

**Option A (in-repo):** The consuming app creates `resources/js/pages/settings/fonnte.tsx` (the plugin docs mention the page route exists). The plugin provides the API routes, the app provides the React page.

**Option B (self-contained):** The plugin ships a simple Blade view instead of an Inertia page, so the settings page works without frontend coupling. This makes the plugin usable even without React.

For now, Option A is simpler — document it in the README.

## Cleanup in core

After extracting:

1. Remove `FonnteDriver::class` import from `config/services.php`
2. Remove the `'fonnte'` entry from `services.whatsapp.drivers`
3. Delete `app/Notifications/Drivers/FonnteDriver.php`
4. Delete `tests/Feature/Notifications/Drivers/FonnteDriverTest.php`
