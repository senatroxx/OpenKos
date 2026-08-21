<?php

namespace App\Rules;

use App\Services\Payments\MoneyConverter;
use Brick\Math\BigDecimal;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MoneyAmount implements ValidationRule
{
    public function __construct(
        private readonly ?string $currency = null,
        private readonly bool $allowZero = true,
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = is_int($value) ? (string) $value : $value;

        if (! is_string($value) || ! preg_match('/\A(?:0|[1-9]\d*)(?:\.\d+)?\z/D', $value)) {
            $fail(__('The :attribute must be a decimal amount.', ['attribute' => $attribute]));

            return;
        }

        try {
            $amount = BigDecimal::of($value);
            if (! $this->allowZero && $amount->isZero()) {
                $fail(__('The :attribute must be greater than zero.', ['attribute' => $attribute]));

                return;
            }

            app(MoneyConverter::class)->normalizeAmount(
                $value,
                app(MoneyConverter::class)->normalizeCurrency($this->currency),
            );
        } catch (\Throwable) {
            $fail(__('The :attribute has more precision than the selected currency supports.', [
                'attribute' => $attribute,
            ]));
        }
    }
}
