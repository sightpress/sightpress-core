<?php

declare(strict_types=1);

namespace SightPress\Core\Install\Domain;

/**
 * What the installer has done so far. It holds only non-secret answers, so the
 * file it is saved to is safe to read, and a failed run can resume.
 */
final readonly class InstallState
{
    /**
     * @param list<string>          $completed steps that passed
     * @param array<string, string> $answers   non-secret answers
     */
    public function __construct(
        public array $completed = [],
        public array $answers = [],
        public ?string $failedStep = null,
        public ?string $failureMessage = null,
        public ?string $installedAt = null,
    ) {
    }

    public function isCompleted(string $step): bool
    {
        return \in_array($step, $this->completed, true);
    }

    public function withCompleted(string $step): self
    {
        $completed = $this->isCompleted($step) ? $this->completed : [...$this->completed, $step];

        return new self($completed, $this->answers, null, null, $this->installedAt);
    }

    public function withAnswer(string $key, string $value): self
    {
        return new self($this->completed, [...$this->answers, $key => $value], $this->failedStep, $this->failureMessage, $this->installedAt);
    }

    public function withFailure(string $step, string $message): self
    {
        return new self($this->completed, $this->answers, $step, $message, $this->installedAt);
    }

    public function withInstalledAt(string $when): self
    {
        return new self($this->completed, $this->answers, null, null, $when);
    }

    /** Forgets progress, but keeps the answers so nothing is asked twice. */
    public function restarted(): self
    {
        return new self([], $this->answers);
    }

    /** @param list<string> $requiredSteps */
    public function hasCompletedAll(array $requiredSteps): bool
    {
        return [] === array_diff($requiredSteps, $this->completed);
    }
}
