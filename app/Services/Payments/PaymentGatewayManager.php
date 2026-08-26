<?php

namespace App\Services\Payments;

use Illuminate\Contracts\Container\Container;
use OpenKOS\Core\Contracts\PaymentGateway;
use OpenKOS\Core\Contracts\PaymentGatewayCurrencySupport;
use OpenKOS\Core\Contracts\PaymentGatewayStatusLookup;
use OpenKOS\Platform\Payment\PaymentRegistry;
use OpenKOS\Platform\Settings\SettingsManager;

class PaymentGatewayManager
{
    public const ACTIVE_KEY = 'payment_gateway';

    public const CONFIG_KEY = 'payment_gateway_config';

    public function __construct(
        private PaymentRegistry $registry,
        private SettingsManager $settings,
        private Container $container,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $gateways = [];

        foreach (array_keys($this->registry->gateways()) as $key) {
            $configuration = $this->configuration($key);
            $gateway = $this->find($key);

            try {
                if (! $gateway) {
                    throw new \RuntimeException('Gateway could not be resolved.');
                }

                $schema = $this->schema($gateway);
                $missing = $this->missingRequired($schema, $configuration);

                $gateways[] = [
                    'key' => $key,
                    'label' => $gateway->displayName(),
                    'configuration_schema' => $this->publicSchema($schema),
                    'configuration' => $this->publicConfiguration($schema, $configuration),
                    'secret_fields' => $this->configuredSecretFields($schema, $configuration),
                    'supported_currencies' => $this->supportedCurrencies($gateway),
                    'status' => $missing === [] ? 'configured' : 'incomplete',
                    'missing_fields' => $missing,
                    'error' => null,
                ];
            } catch (\Throwable) {
                $gateways[] = [
                    'key' => $key,
                    'label' => $key,
                    'configuration_schema' => [],
                    'configuration' => [],
                    'secret_fields' => [],
                    'supported_currencies' => null,
                    'status' => 'unavailable',
                    'missing_fields' => [],
                    'error' => 'This payment gateway is unavailable.',
                ];
            }
        }

        return $gateways;
    }

    public function find(string $key): ?PaymentGateway
    {
        $registered = $this->registry->gateways()[$key] ?? null;

        if ($registered instanceof PaymentGateway) {
            try {
                return $registered->key() === $key ? $registered : null;
            } catch (\Throwable) {
                return null;
            }
        }

        if (! is_string($registered)) {
            return null;
        }

        try {
            $gateway = $this->container->make($registered, [
                'config' => $this->configuration($key),
            ]);
        } catch (\Throwable) {
            return null;
        }

        if (! $gateway instanceof PaymentGateway) {
            return null;
        }

        try {
            return $gateway->key() === $key ? $gateway : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function active(): ?PaymentGateway
    {
        $key = $this->activeKey();

        if ($key === null) {
            return null;
        }

        $gateway = $this->find($key);

        if (! $gateway) {
            return null;
        }

        try {
            return $this->missingRequired(
                $this->schema($gateway),
                $this->configuration($key),
            ) === [] ? $gateway : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function supportsStatusLookup(string $key): bool
    {
        return $this->find($key) instanceof PaymentGatewayStatusLookup;
    }

    public function supportsCurrency(PaymentGateway $gateway, string $currency): ?bool
    {
        if (! interface_exists(PaymentGatewayCurrencySupport::class)
            || ! $gateway instanceof PaymentGatewayCurrencySupport) {
            return null;
        }

        try {
            $declared = $this->supportedCurrencies($gateway);

            return $gateway->supportsCurrency($currency)
                && in_array(strtoupper(trim($currency)), $declared, true);
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * @return list<string>|null
     */
    public function supportedCurrencies(PaymentGateway $gateway): ?array
    {
        if (! interface_exists(PaymentGatewayCurrencySupport::class)
            || ! $gateway instanceof PaymentGatewayCurrencySupport) {
            return null;
        }

        $currencies = $gateway->supportedCurrencies();

        if (! array_is_list($currencies)) {
            throw new \RuntimeException('Payment gateway supported currencies must be a list.');
        }

        $normalized = [];

        foreach ($currencies as $currency) {
            if (! is_string($currency) || ! preg_match('/\A[A-Z]{3}\z/D', strtoupper(trim($currency)))) {
                throw new \RuntimeException('Payment gateway supported currencies must be ISO 4217 codes.');
            }

            $normalized[] = strtoupper(trim($currency));
        }

        return array_values(array_unique($normalized));
    }

    public function activeKey(): ?string
    {
        try {
            $key = $this->settings->get(self::ACTIVE_KEY);
        } catch (\Throwable) {
            return null;
        }

        return is_string($key) && $key !== '' ? $key : null;
    }

    /** @return array<string, mixed> */
    public function configuration(string $key): array
    {
        try {
            $configurations = $this->settings->get(self::CONFIG_KEY);
        } catch (\Throwable) {
            return [];
        }

        $configuration = is_array($configurations) ? ($configurations[$key] ?? []) : [];

        return is_array($configuration) ? $configuration : [];
    }

    /** @return array<string, array<string, mixed>> */
    public function configurations(): array
    {
        try {
            $configurations = $this->settings->get(self::CONFIG_KEY);
        } catch (\Throwable) {
            return [];
        }

        return is_array($configurations) ? $configurations : [];
    }

    /** @return array<string, array<string, mixed>> */
    private function schema(PaymentGateway $gateway): array
    {
        $schema = $gateway->configurationSchema();

        if (! is_array($schema)) {
            throw new \RuntimeException('Gateway configuration schema must be an array.');
        }

        foreach ($schema as $field => $definition) {
            if (! is_string($field) || ! is_array($definition)) {
                throw new \RuntimeException('Gateway configuration schema is invalid.');
            }
        }

        return $schema;
    }

    /** @return array<string, array<string, mixed>> */
    private function publicSchema(array $schema): array
    {
        $public = [];

        foreach ($schema as $key => $field) {
            $public[$key] = array_filter([
                'label' => $field['label'] ?? $key,
                'type' => $field['type'] ?? 'text',
                'required' => (bool) ($field['required'] ?? false),
                'placeholder' => $field['placeholder'] ?? null,
                'description' => $field['description'] ?? null,
                'instructions' => $field['instructions'] ?? null,
                'link' => $field['link'] ?? null,
                'url' => $field['url'] ?? null,
                'options' => $field['options'] ?? null,
                'presentation' => $field['presentation'] ?? null,
                'default' => $field['default'] ?? null,
                'visible_when' => $field['visible_when'] ?? null,
                'secret' => $this->isSecretField($field),
            ], static fn (mixed $value): bool => $value !== null);
        }

        return $public;
    }

    /** @return array<string, mixed> */
    private function publicConfiguration(array $schema, array $configuration): array
    {
        $public = [];

        foreach ($schema as $key => $field) {
            if (! $this->isSecretField($field) && array_key_exists($key, $configuration)) {
                $public[$key] = $configuration[$key];
            }
        }

        return $public;
    }

    /** @return array<int, string> */
    private function configuredSecretFields(array $schema, array $configuration): array
    {
        $fields = [];

        foreach ($schema as $key => $field) {
            if ($this->isSecretField($field) && filled($configuration[$key] ?? null)) {
                $fields[] = $key;
            }
        }

        return $fields;
    }

    /** @return array<int, string> */
    private function missingRequired(array $schema, array $configuration): array
    {
        $missing = [];

        foreach ($schema as $key => $field) {
            if (($field['required'] ?? false) && blank($configuration[$key] ?? null)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    private function isSecretField(array $field): bool
    {
        return ($field['secret'] ?? false) === true
            || in_array(strtolower((string) ($field['type'] ?? '')), [
                'password',
                'secret',
                'token',
                'api_key',
                'encrypted',
            ], true);
    }
}
