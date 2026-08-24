<?php

namespace App\Services\Payments;

use App\Models\Setting;
use App\Services\Localization\ApplicationLocale;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use OpenKOS\Core\Data\Payment\Money;

final class MoneyConverter
{
    private const MAX_INTEGER_DIGITS = 17;

    public function __construct(private ?ApplicationLocale $localeResolver = null) {}

    /**
     * Application billing scales for supported ISO 4217 currencies.
     *
     * @var array<string, int>
     */
    private const MINOR_UNITS = [
        'AED' => 2, 'AFN' => 2, 'ALL' => 2, 'AMD' => 2, 'ANG' => 2,
        'AOA' => 2, 'ARS' => 2, 'AUD' => 2, 'AWG' => 2, 'AZN' => 2,
        'BAM' => 2, 'BBD' => 2, 'BDT' => 2, 'BGN' => 2, 'BHD' => 3,
        'BIF' => 0, 'BMD' => 2, 'BND' => 2, 'BOB' => 2, 'BRL' => 2,
        'BSD' => 2, 'BTN' => 2, 'BWP' => 2, 'BYN' => 2, 'BZD' => 2,
        'CAD' => 2, 'CDF' => 2, 'CHF' => 2, 'CNY' => 2, 'COP' => 2,
        'CRC' => 2, 'CUP' => 2, 'CVE' => 2, 'CZK' => 2, 'DJF' => 0,
        'DKK' => 2, 'DOP' => 2, 'DZD' => 2, 'EGP' => 2, 'ERN' => 2,
        'ETB' => 2, 'EUR' => 2, 'FJD' => 2, 'FKP' => 2, 'GBP' => 2,
        'GEL' => 2, 'GHS' => 2, 'GMD' => 2, 'GIP' => 2, 'GNF' => 0,
        'GTQ' => 2, 'GYD' => 2, 'HKD' => 2, 'HNL' => 2, 'HTG' => 2,
        'HUF' => 2, 'IDR' => 0, 'ILS' => 2, 'INR' => 2, 'IQD' => 3,
        'IRR' => 2, 'ISK' => 0, 'JMD' => 2, 'JOD' => 3, 'JPY' => 0,
        'KES' => 2, 'KGS' => 2, 'KHR' => 2, 'KMF' => 0, 'KPW' => 2,
        'KRW' => 0, 'KWD' => 3, 'KYD' => 2, 'KZT' => 2, 'LAK' => 2,
        'LBP' => 2, 'LKR' => 2, 'LRD' => 2, 'LSL' => 2, 'LYD' => 3,
        'MAD' => 2, 'MDL' => 2, 'MGA' => 2, 'MKD' => 2, 'MMK' => 2,
        'MNT' => 2, 'MOP' => 2, 'MRU' => 2, 'MUR' => 2, 'MVR' => 2,
        'MWK' => 2, 'MXN' => 2, 'MYR' => 2, 'MZN' => 2, 'NAD' => 2,
        'NGN' => 2, 'NIO' => 2, 'NOK' => 2, 'NPR' => 2, 'NZD' => 2,
        'OMR' => 3, 'PAB' => 2, 'PEN' => 2, 'PGK' => 2, 'PHP' => 2,
        'PKR' => 2, 'PLN' => 2, 'PYG' => 0, 'QAR' => 2, 'RON' => 2,
        'RSD' => 2, 'RUB' => 2, 'RWF' => 0, 'SAR' => 2, 'SBD' => 2,
        'SCR' => 2, 'SDG' => 2, 'SEK' => 2, 'SGD' => 2, 'SHP' => 2,
        'SLE' => 2, 'SOS' => 2, 'SRD' => 2, 'SSP' => 2, 'STN' => 2,
        'SVC' => 2, 'SYP' => 2, 'SZL' => 2, 'THB' => 2, 'TJS' => 2,
        'TMT' => 2, 'TND' => 3, 'TOP' => 2, 'TRY' => 2, 'TTD' => 2,
        'TWD' => 2, 'TZS' => 2, 'UAH' => 2, 'UGX' => 0, 'USD' => 2,
        'UYU' => 2, 'UZS' => 2, 'VES' => 2, 'VND' => 0, 'VUV' => 0,
        'WST' => 2, 'XAF' => 0, 'XCD' => 2, 'XOF' => 0, 'XPF' => 0,
        'YER' => 2, 'ZAR' => 2, 'ZMW' => 2, 'ZWG' => 2,
    ];

    /**
     * @return array<string, int>
     */
    public function scales(): array
    {
        return self::MINOR_UNITS;
    }

    public function normalizeCurrency(?string $currency = null): string
    {
        $currency = strtoupper(trim($currency ?? (string) (Setting::get('currency') ?? 'IDR')));

        if (! preg_match('/\A[A-Z]{3}\z/D', $currency)) {
            throw new InvalidArgumentException('Currency must be a three-letter ISO 4217 code.');
        }

        if (! array_key_exists($currency, self::MINOR_UNITS)) {
            throw new InvalidArgumentException(
                "Minor-unit scale is not configured for currency [{$currency}].",
            );
        }

        return $currency;
    }

    public function scale(string $currency): int
    {
        return self::MINOR_UNITS[$this->normalizeCurrency($currency)];
    }

    public function normalizeAmount(string $majorAmount, string $currency): string
    {
        $currency = $this->normalizeCurrency($currency);
        $scale = $this->scale($currency);

        try {
            $amount = BigDecimal::of($majorAmount);

            if ($amount->isNegative()) {
                throw new InvalidArgumentException('Money amounts cannot be negative.');
            }

            $normalized = $amount
                ->toScale($scale, RoundingMode::Unnecessary)
                ->toString();

            $integerDigits = max(
                1,
                strlen($amount->toScale($scale, RoundingMode::Unnecessary)->getUnscaledValue()->abs()->toString()) - $scale,
            );

            if ($integerDigits > self::MAX_INTEGER_DIGITS) {
                throw new InvalidArgumentException(
                    'Amount exceeds the 17-digit integer limit for decimal(20,3) storage.',
                );
            }

            return $normalized;
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException(
                'Amount has more precision than the configured currency supports.',
                previous: $exception,
            );
        }
    }

    public function compare(string $left, string $right): int
    {
        return BigDecimal::of($left)->compareTo(BigDecimal::of($right));
    }

    public function subtract(string $left, string $right, string $currency): string
    {
        $this->normalizeCurrency($currency);

        return BigDecimal::of($left)
            ->minus($right)
            ->toString();
    }

    public function toMoney(string $majorAmount, string $currency): Money
    {
        $currency = $this->normalizeCurrency($currency);
        $scale = $this->scale($currency);

        try {
            $amount = BigDecimal::of($majorAmount);

            if ($amount->isNegative()) {
                throw new InvalidArgumentException('Money amounts cannot be negative.');
            }

            $minorUnits = $amount
                ->multipliedBy(10 ** $scale)
                ->toScale(0, RoundingMode::Unnecessary)
                ->toInt();
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException(
                'Amount has more precision than the configured currency supports.',
                previous: $exception,
            );
        }

        return new Money($minorUnits, $currency);
    }

    public function format(string $majorAmount, string $currency, ?string $locale = null): string
    {
        $currency = $this->normalizeCurrency($currency);
        $scale = $this->scale($currency);
        $amount = BigDecimal::of($majorAmount)->toScale($scale, RoundingMode::HalfEven);
        $absolute = $amount->abs()->toScale($scale, RoundingMode::Unnecessary)->toString();
        [$integer, $fraction] = array_pad(explode('.', $absolute, 2), 2, '');

        $localeResolver = $this->localeResolver ?? new ApplicationLocale;
        $displayLocale = $localeResolver->intlLocale($locale);
        $formatter = new \NumberFormatter(
            $displayLocale,
            \NumberFormatter::CURRENCY,
        );
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $scale);

        $groupingSeparator = $formatter->getSymbol(\NumberFormatter::MONETARY_GROUPING_SEPARATOR_SYMBOL);
        $decimalSeparator = $formatter->getSymbol(\NumberFormatter::MONETARY_SEPARATOR_SYMBOL);
        $pattern = (string) $formatter->getPattern();
        preg_match('/([#,]+0)(?:\.[0#]+)?/', $pattern, $matches);
        $integerPattern = $matches[1] ?? '#,##0';
        $groups = explode(',', $integerPattern);
        $primaryGroupSize = strlen((string) end($groups));
        $secondaryGroupSize = count($groups) > 2
            ? strlen((string) $groups[count($groups) - 2])
            : $primaryGroupSize;
        $formattedInteger = $this->groupInteger(
            $integer,
            $groupingSeparator,
            $primaryGroupSize,
            $secondaryGroupSize,
        );
        $formattedNumber = $formattedInteger.($scale > 0 ? $decimalSeparator.$fraction : '');
        $template = $formatter->formatCurrency($amount->isNegative() ? -1.0 : 1.0, $currency);
        $templateNumber = '1'.($scale > 0 ? $decimalSeparator.str_repeat('0', $scale) : '');

        return is_string($template)
            ? str_replace($templateNumber, $formattedNumber, $template)
            : $formattedNumber;
    }

    private function groupInteger(
        string $integer,
        string $separator,
        int $primaryGroupSize,
        int $secondaryGroupSize,
    ): string {
        if (strlen($integer) <= $primaryGroupSize) {
            return $integer;
        }

        $groups = [substr($integer, -$primaryGroupSize)];
        $integer = substr($integer, 0, -$primaryGroupSize);

        while ($integer !== '') {
            $groups[] = substr($integer, -$secondaryGroupSize);
            $integer = substr($integer, 0, -$secondaryGroupSize);
        }

        return implode($separator, array_reverse($groups));
    }
}
