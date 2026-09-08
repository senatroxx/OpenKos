<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\InstallPluginRequest;
use App\Http\Requests\Settings\MarketplaceActionRequest;
use App\Http\Requests\Settings\MarketplaceBrowseRequest;
use App\Services\Platform\PluginManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PluginController extends Controller
{
    public function index(PluginManagementService $plugins): Response
    {
        $catalog = $plugins->catalog();

        return Inertia::render('settings/plugins', [
            'plugins' => $catalog['plugins'],
            'error' => $catalog['error'],
            'max_upload_bytes' => (int) config('platform.runtime.max_upload_bytes', 64 * 1024 * 1024),
        ]);
    }

    public function install(InstallPluginRequest $request, PluginManagementService $plugins): RedirectResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');

        try {
            $plugins->install($file);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'file' => $plugins->userMessage($exception),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Plugin installed. Restart required for changes to take effect.'),
        ]);

        return to_route('settings.plugins.index');
    }

    public function marketplace(MarketplaceBrowseRequest $request, PluginManagementService $plugins): JsonResponse
    {
        return response()->json($plugins->marketplaceCatalog(
            $request->string('q')->trim()->toString() ?: null,
            $request->integer('page', 1),
            $request->integer('limit', 20),
        ));
    }

    public function marketplaceInstall(MarketplaceActionRequest $request, PluginManagementService $plugins): RedirectResponse
    {
        return $this->runMarketplaceAction($request, $plugins, false);
    }

    public function marketplaceUpdate(MarketplaceActionRequest $request, PluginManagementService $plugins): RedirectResponse
    {
        return $this->runMarketplaceAction($request, $plugins, true);
    }

    public function enable(string $vendor, string $package, PluginManagementService $plugins): RedirectResponse
    {
        return $this->runLifecycle($plugins, $vendor.'/'.$package, fn (string $id): mixed => $plugins->enable($id), __('Plugin enabled. Restart required for changes to take effect.'));
    }

    public function disable(Request $request, string $vendor, string $package, PluginManagementService $plugins): RedirectResponse
    {
        return $this->runLifecycle($plugins, $vendor.'/'.$package, fn (string $id): mixed => $plugins->disable($id, $request->boolean('force')), __('Plugin disabled. Restart required for changes to take effect.'));
    }

    public function destroy(Request $request, string $vendor, string $package, PluginManagementService $plugins): RedirectResponse
    {
        return $this->runLifecycle($plugins, $vendor.'/'.$package, fn (string $id): mixed => $plugins->remove($id, $request->boolean('force')), __('Plugin removed. Restart required for changes to take effect.'));
    }

    public function cleanup(Request $request, PluginManagementService $plugins): RedirectResponse
    {
        try {
            $plugins->cleanupOrphanedMetadata(
                $request->string('recovery_id')->trim()->toString() ?: null,
                $request->string('cleanup_key')->trim()->toString() ?: null,
            );
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'plugin' => $plugins->userMessage($exception),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Runtime plugin metadata cleaned.')]);

        return to_route('settings.plugins.index');
    }

    /** @param callable(string): mixed $action */
    private function runLifecycle(PluginManagementService $plugins, string $id, callable $action, string $success): RedirectResponse
    {
        try {
            $action($id);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'plugin' => $plugins->userMessage($exception),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $success]);

        return to_route('settings.plugins.index');
    }

    private function runMarketplaceAction(MarketplaceActionRequest $request, PluginManagementService $plugins, bool $update): RedirectResponse
    {
        $pluginId = $request->string('plugin_id')->toString();
        $version = $request->string('version')->toString();

        try {
            if ($update) {
                $plugins->updateFromMarketplace($pluginId, $version);
            } else {
                $plugins->installFromMarketplace($pluginId, $version);
            }
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'marketplace' => $plugins->userMessage($exception),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $update
                ? __('Plugin updated from the marketplace. Restart required for changes to take effect.')
                : __('Plugin installed from the marketplace. Restart required for changes to take effect.'),
        ]);

        return to_route('settings.plugins.index');
    }
}
