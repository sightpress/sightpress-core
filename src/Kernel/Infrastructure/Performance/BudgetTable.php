<?php

declare(strict_types=1);

namespace SightPress\Core\Kernel\Infrastructure\Performance;

use Symfony\Component\Yaml\Yaml;

/** The performance budgets, loaded from config/budgets.yaml. */
final readonly class BudgetTable
{
    /** @param array<string, Budget> $budgets */
    private function __construct(public array $budgets)
    {
    }

    public static function fromFile(string $file): self
    {
        if (!is_file($file)) {
            throw new \InvalidArgumentException(\sprintf('The budget file "%s" does not exist.', $file));
        }

        $data = Yaml::parseFile($file);
        $entries = \is_array($data) && \is_array($data['budgets'] ?? null) ? $data['budgets'] : [];

        $budgets = [];
        foreach ($entries as $name => $entry) {
            if (!\is_string($name) || !\is_array($entry) || !\is_int($entry['limit'] ?? null) && !\is_float($entry['limit'] ?? null)) {
                throw new \InvalidArgumentException(\sprintf('Budget "%s" needs a numeric limit.', (string) $name));
            }
            $rule = $entry['rule'] ?? 'lt';
            if (!\in_array($rule, ['lt', 'lte'], true)) {
                throw new \InvalidArgumentException(\sprintf('Budget "%s" has an unknown rule; use "lt" or "lte".', $name));
            }
            $description = $entry['description'] ?? $name;
            $budgets[$name] = new Budget($name, $entry['limit'], $rule, \is_string($description) ? $description : $name);
        }

        return new self($budgets);
    }

    public static function default(): self
    {
        return self::fromFile(\dirname(__DIR__, 4).'/config/budgets.yaml');
    }
}
