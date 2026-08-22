<?php

namespace App\Actions\Settings;

use App\Models\Setting;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class UpdateBranding
{
    private const SETTING_KEYS = [
        'logo' => 'branding_logo_path',
        'favicon' => 'branding_favicon_path',
    ];

    public function __construct(
        private UpdateSettings $updateSettings,
    ) {}

    public function execute(string $asset, UploadedFile $file, Authenticatable $actor): void
    {
        $key = $this->settingKey($asset);
        $disk = $this->disk();
        $previousPath = (string) Setting::get($key);
        $newPath = null;

        try {
            $newPath = $file->store('branding', $disk);

            if ($newPath === false) {
                throw new RuntimeException('Branding file could not be stored.');
            }

            $this->updateSettings->execute([$key => $newPath], $actor);
        } catch (Throwable $exception) {
            $settingsState = is_string($newPath)
                ? $this->settingMatches($key, $newPath)
                : false;

            if ($settingsState === true && $previousPath !== $newPath) {
                $this->delete($disk, $previousPath);
            } elseif ($settingsState === false && is_string($newPath)) {
                $this->delete($disk, $newPath);
            }

            throw $exception;
        }

        if ($previousPath !== $newPath) {
            $this->delete($disk, $previousPath);
        }
    }

    public function remove(string $asset, Authenticatable $actor): void
    {
        $key = $this->settingKey($asset);
        $disk = $this->disk();
        $previousPath = (string) Setting::get($key);

        try {
            $this->updateSettings->execute([$key => ''], $actor);
        } catch (Throwable $exception) {
            if ($this->settingMatches($key, '') === true) {
                $this->delete($disk, $previousPath);
            }

            throw $exception;
        }

        $this->delete($disk, $previousPath);
    }

    private function settingKey(string $asset): string
    {
        return self::SETTING_KEYS[$asset]
            ?? throw new InvalidArgumentException("Unsupported branding asset [{$asset}].");
    }

    private function disk(): string
    {
        return (string) config('filesystems.default', 'local');
    }

    private function delete(string $disk, string $path): void
    {
        if (filled($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    private function settingMatches(string $key, string $value): ?bool
    {
        try {
            return Setting::get($key) === $value;
        } catch (Throwable) {
            return null;
        }
    }
}
