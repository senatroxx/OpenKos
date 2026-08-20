<?php

namespace Tests\Support\Fixtures;

final class PackageUninstallEvent
{
    public function __construct(private readonly array $extra = []) {}

    public function getOperation(): object
    {
        return new class($this->extra)
        {
            public function __construct(private readonly array $extra) {}

            public function getPackage(): object
            {
                return new class($this->extra)
                {
                    public function __construct(private readonly array $extra) {}

                    public function getName(): string
                    {
                        return 'vendor/payment-package';
                    }

                    public function getExtra(): array
                    {
                        return $this->extra;
                    }
                };
            }
        };
    }
}
