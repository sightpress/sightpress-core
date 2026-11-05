<?php

declare(strict_types=1);

namespace SightPress\Core\Install\Infrastructure;

use SightPress\Core\Install\Domain\InstallState;
use SightPress\Core\Install\Domain\InstallStateStore;

/** Keeps the installer's progress in a JSON file under var/. It holds no secrets. */
final readonly class FileInstallStateStore implements InstallStateStore
{
    public function __construct(private string $file)
    {
    }

    public function load(): InstallState
    {
        if (!is_file($this->file)) {
            return new InstallState();
        }
        $data = json_decode((string) file_get_contents($this->file), true);
        if (!\is_array($data)) {
            return new InstallState();
        }

        $completed = array_values(array_filter(\is_array($data['completed'] ?? null) ? $data['completed'] : [], \is_string(...)));
        $answers = [];
        foreach (\is_array($data['answers'] ?? null) ? $data['answers'] : [] as $key => $value) {
            if (\is_string($key) && \is_string($value)) {
                $answers[$key] = $value;
            }
        }
        $string = static fn (mixed $value): ?string => \is_string($value) ? $value : null;

        return new InstallState($completed, $answers, $string($data['failedStep'] ?? null), $string($data['failureMessage'] ?? null), $string($data['installedAt'] ?? null));
    }

    public function save(InstallState $state): void
    {
        $directory = \dirname($this->file);
        if (!is_dir($directory) && !@mkdir($directory, 0o750, true) && !is_dir($directory)) {
            throw new \RuntimeException(\sprintf('The installer could not create %s to save its progress. Check that var/ is writable.', $directory));
        }
        $json = json_encode([
            'completed' => $state->completed,
            'answers' => $state->answers,
            'failedStep' => $state->failedStep,
            'failureMessage' => $state->failureMessage,
            'installedAt' => $state->installedAt,
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
        if (false === @file_put_contents($this->file, $json."\n", \LOCK_EX)) {
            throw new \RuntimeException(\sprintf('The installer could not save its progress to %s. Check that var/ is writable.', $this->file));
        }
        @chmod($this->file, 0o640);
    }
}
