<?php

declare(strict_types=1);

namespace SightPress\Core\Install\Infrastructure;

use SightPress\Core\Install\Domain\ConfigWriter;
use Symfony\Bundle\FrameworkBundle\Secrets\SodiumVault;

/**
 * Writes .env.local (only this machine's values, never committed) and, in
 * production, seals secrets into the vault in config/secrets/prod. Existing
 * lines for other variables are kept.
 */
final readonly class ProjectConfigWriter implements ConfigWriter
{
    public function __construct(private string $projectDir)
    {
    }

    public function write(array $values, #[\SensitiveParameter] array $secrets, bool $useVault): array
    {
        $written = [];
        $envValues = $useVault ? $values : [...$values, ...$secrets];
        if ($useVault) {
            $vault = new SodiumVault($this->projectDir.'/config/secrets/prod');
            $vault->generateKeys();
            foreach ($secrets as $name => $value) {
                $vault->seal($name, $value);
                // A value left in .env.local would override the vault.
                $envValues[$name] = null;
            }
            $written[] = 'config/secrets/prod';
        }

        $this->writeEnvFile(array_filter($envValues, static fn (?string $v): bool => null !== $v), array_keys(array_filter($envValues, static fn (?string $v): bool => null === $v)));
        $written[] = '.env.local';

        return $written;
    }

    public function current(string $name): ?string
    {
        foreach ($this->lines() as $line) {
            if (1 === preg_match('/^\s*(?:export\s+)?'.preg_quote($name, '/').'\s*=\s*(.*)$/', $line, $match)) {
                return $this->unquote(trim($match[1]));
            }
        }

        return null;
    }

    public function currentSecret(string $name): ?string
    {
        $vault = new SodiumVault($this->projectDir.'/config/secrets/prod');
        $value = is_file($this->projectDir.'/config/secrets/prod/prod.decrypt.private.php') ? $vault->reveal($name) : null;

        return $value ?? $this->current($name);
    }

    /**
     * @param array<string, string> $values
     * @param list<string>          $remove
     */
    private function writeEnvFile(array $values, array $remove): void
    {
        $file = $this->projectDir.'/.env.local';
        $lines = $this->lines();
        $seen = [];
        foreach ($lines as $index => $line) {
            if (1 !== preg_match('/^\s*(?:export\s+)?([A-Z0-9_]+)\s*=/', $line, $match)) {
                continue;
            }
            $name = $match[1];
            if (\in_array($name, $remove, true)) {
                unset($lines[$index]);
            } elseif (\array_key_exists($name, $values)) {
                $lines[$index] = $name.'='.$this->quote($values[$name]);
                $seen[$name] = true;
            }
        }
        if ([] === $lines) {
            $lines[] = '# This machine\'s settings, written by sightpress:install. Never commit this file.';
        }
        foreach ($values as $name => $value) {
            if (!isset($seen[$name])) {
                $lines[] = $name.'='.$this->quote($value);
            }
        }

        $existed = is_file($file);
        if (false === @file_put_contents($file, implode("\n", $lines)."\n", \LOCK_EX)) {
            throw new \RuntimeException('The installer could not write .env.local. Check that the project folder is writable.');
        }
        if (!$existed) {
            @chmod($file, 0o600);
        }
    }

    /** @return list<string> */
    private function lines(): array
    {
        $file = $this->projectDir.'/.env.local';
        if (!is_file($file)) {
            return [];
        }

        return array_values(array_filter(preg_split('/\R/', (string) file_get_contents($file)) ?: [], static fn (string $l): bool => '' !== $l));
    }

    private function quote(string $value): string
    {
        if (1 === preg_match('/^[A-Za-z0-9_.\/:@+-]*$/', $value)) {
            return $value;
        }

        // Single quotes: Dotenv takes the value literally, so "$" and "\" stay as typed.
        return "'".str_replace("'", "'\\''", $value)."'";
    }

    private function unquote(string $value): string
    {
        if (1 === preg_match("/^'(.*)'$/s", $value, $match)) {
            return str_replace("'\\''", "'", $match[1]);
        }
        if (1 === preg_match('/^"(.*)"$/s', $value, $match)) {
            return stripcslashes($match[1]);
        }

        return $value;
    }
}
