<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\InstallPluginRequest;
use App\Services\Platform\PluginManagementService;
use Illuminate\Http\RedirectResponse;
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

    public function enable(string $vendor, string $package, PluginManagementService $plugins): RedirectResponse
    {
        return $this->runLifecycle($plugins, $vendor.'/'.$package, fn (string $id): mixed => $plugins->enable($id), __('Plugin enabled. Restart required for changes to take effect.'));
    }

    public function disable(string $vendor, string $package, PluginManagementService $plugins): RedirectResponse
    {
        return $this->runLifecycle($plugins, $vendor.'/'.$package, fn (string $id): mixed => $plugins->disable($id), __('Plugin disabled. Restart required for changes to take effect.'));
    }

    public function destroy(string $vendor, string $package, PluginManagementService $plugins): RedirectResponse
    {
        return $this->runLifecycle($plugins, $vendor.'/'.$package, fn (string $id): mixed => $plugins->remove($id), __('Plugin removed. Restart required for changes to take effect.'));
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
}
