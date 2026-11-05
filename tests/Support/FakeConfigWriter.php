<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Support;

use SightPress\Core\Install\Domain\ConfigWriter;

final class FakeConfigWriter implements ConfigWriter
{
    public ?bool $usedVault = null;

    /**
     * @param array<string, string> $values
     * @param array<string, string> $secrets
     */
    public function __construct(public array $values = [], public array $secrets = [])
    {
    }

    public function write(array $values, #[\SensitiveParameter] array $secrets, bool $useVault): array
    {
        $this->values = [...$this->values, ...$values];
        $this->secrets = [...$this->secrets, ...$secrets];
        $this->usedVault = $useVault;

        return $useVault ? ['config/secrets/prod', '.env.local'] : ['.env.local'];
    }

    public function current(string $name): ?string
    {
        return $this->values[$name] ?? null;
    }

    public function currentSecret(string $name): ?string
    {
        return $this->secrets[$name] ?? null;
    }
}
