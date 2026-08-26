<?php

use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenKOS\Platform\Payment\PaymentRegistry;
use OpenKOS\Platform\Settings\SettingsManager;
use Tests\Support\Fakes\BrokenManagerTestPaymentGateway;
use Tests\Support\Fakes\CurrencyAwareManagerTestPaymentGateway;
use Tests\Support\Fakes\MalformedCurrencyManagerTestPaymentGateway;
use Tests\Support\Fakes\ManagerTestPaymentGateway;
use Tests\Support\Fakes\MismatchedManagerTestPaymentGateway;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns provider metadata without exposing secret values', function () {
    $registry = new PaymentRegistry;
    $registry->registerGateway('test/gateway', ManagerTestPaymentGateway::class);
    $settings = app(SettingsManager::class);
    $settings->set(PaymentGatewayManager::CONFIG_KEY, [
        'test/gateway' => [
            'environment' => 'sandbox',
            'secret_key' => 'top-secret',
        ],
    ]);

    $gateway = new PaymentGatewayManager($registry, $settings, app());
    $provider = $gateway->all()[0];

    expect($provider['status'])->toBe('configured')
        ->and($provider['configuration'])->toBe(['environment' => 'sandbox'])
        ->and($provider['secret_fields'])->toBe(['secret_key'])
        ->and($provider['configuration_schema']['environment']['presentation'])->toBe('segmented')
        ->and($provider['configuration_schema']['environment']['default'])->toBe('sandbox')
        ->and($provider['configuration_schema']['webhook_setup']['instructions'])->toBe([
            'Open the webhook settings.',
            'Add the webhook URL shown below.',
        ])
        ->and($provider['configuration_schema']['webhook_setup']['link'])->toBe([
            'label' => 'Open webhook settings',
            'url' => 'https://example.test/webhooks',
        ])
        ->and($provider['configuration_schema']['webhook_setup']['url'])->toBe('/api/webhooks/test')
        ->and($provider['configuration_schema']['secret_key']['description'])->toBe('Keep this value secret.')
        ->and($provider['configuration_schema']['secret_key']['visible_when'])->toBe([
            'field' => 'environment',
            'value' => 'sandbox',
        ])
        ->and($provider['supported_currencies'])->toBeNull();
});

it('exposes and enforces declared gateway currencies', function () {
    $registry = new PaymentRegistry;
    $registry->registerGateway('currency-aware', CurrencyAwareManagerTestPaymentGateway::class);
    $manager = new PaymentGatewayManager($registry, app(SettingsManager::class), app());
    $gateway = $manager->find('currency-aware');

    expect($gateway)->not->toBeNull()
        ->and($manager->supportedCurrencies($gateway))->toBe(['IDR'])
        ->and($manager->supportsCurrency($gateway, 'idr'))->toBeTrue()
        ->and($manager->supportsCurrency($gateway, 'USD'))->toBeFalse();
});

it('fails closed for malformed gateway currency declarations', function () {
    $registry = new PaymentRegistry;
    $registry->registerGateway('malformed-currency', MalformedCurrencyManagerTestPaymentGateway::class);
    $manager = new PaymentGatewayManager($registry, app(SettingsManager::class), app());
    $gateway = $manager->find('malformed-currency');

    expect($gateway)->not->toBeNull()
        ->and($manager->supportsCurrency($gateway, 'IDR'))->toBeFalse()
        ->and($manager->all()[0]['status'])->toBe('unavailable');
});

it('preserves an explicitly empty currency declaration', function () {
    $gateway = new class extends CurrencyAwareManagerTestPaymentGateway
    {
        public function key(): string
        {
            return 'no-currencies';
        }

        /**
         * @return list<string>
         */
        public function supportedCurrencies(): array
        {
            return [];
        }
    };
    $registry = new PaymentRegistry;
    $registry->registerGateway('no-currencies', $gateway);
    $manager = new PaymentGatewayManager($registry, app(SettingsManager::class), app());

    expect($manager->supportedCurrencies($gateway))->toBe([])
        ->and($manager->supportsCurrency($gateway, 'IDR'))->toBeFalse();
});

it('resolves only a configured active gateway', function () {
    $registry = new PaymentRegistry;
    $registry->registerGateway('test/gateway', ManagerTestPaymentGateway::class);
    $settings = app(SettingsManager::class);
    $settings->set(PaymentGatewayManager::CONFIG_KEY, [
        'test/gateway' => [
            'environment' => 'sandbox',
            'secret_key' => 'top-secret',
        ],
    ]);
    $settings->set(PaymentGatewayManager::ACTIVE_KEY, 'test/gateway');

    $gateway = new PaymentGatewayManager($registry, $settings, app());

    expect($gateway->activeKey())->toBe('test/gateway')
        ->and($gateway->find('test/gateway'))->toBeInstanceOf(ManagerTestPaymentGateway::class)
        ->and($gateway->active())->toBeInstanceOf(ManagerTestPaymentGateway::class);

    $settings->set(PaymentGatewayManager::ACTIVE_KEY, 'missing/gateway');

    expect($gateway->activeKey())->toBe('missing/gateway')
        ->and($gateway->find('missing/gateway'))->toBeNull()
        ->and($gateway->active())->toBeNull();
});

it('keeps broken providers visible without making enumeration throw', function () {
    $registry = new PaymentRegistry;
    $registry->registerGateway('broken/gateway', BrokenManagerTestPaymentGateway::class);
    $settings = app(SettingsManager::class);

    $gateway = new PaymentGatewayManager($registry, $settings, app());
    $provider = $gateway->all()[0];

    expect($provider['status'])->toBe('unavailable')
        ->and($provider['error'])->toBe('This payment gateway is unavailable.')
        ->and($provider['supported_currencies'])->toBeNull()
        ->and($gateway->find('broken/gateway'))->toBeNull();
});

it('rejects gateways whose contract key differs from the registry key', function () {
    $registry = new PaymentRegistry;
    $registry->registerGateway('registry/gateway', MismatchedManagerTestPaymentGateway::class);

    $gateway = new PaymentGatewayManager($registry, app(SettingsManager::class), app());

    expect($gateway->find('registry/gateway'))->toBeNull()
        ->and($gateway->all()[0]['status'])->toBe('unavailable');
});
