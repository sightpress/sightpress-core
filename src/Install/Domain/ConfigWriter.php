<?php

declare(strict_types=1);

namespace SightPress\Core\Install\Domain;

/** Saves the answers where the application reads them. Secrets are never logged or printed. */
interface ConfigWriter
{
    /**
     * @param array<string, string> $values  non-secret variables, written to .env.local
     * @param array<string, string> $secrets secret variables: .env.local in dev, the secrets vault in prod
     *
     * @return list<string> the files written, relative to the project, for the summary
     */
    public function write(array $values, #[\SensitiveParameter] array $secrets, bool $useVault): array;

    /** A non-secret value already in .env.local, so a re-run can offer it as the default. */
    public function current(string $name): ?string;

    /** A secret saved by an earlier run (.env.local or the vault), so a re-run can keep it. Never printed. */
    public function currentSecret(string $name): ?string;
}
