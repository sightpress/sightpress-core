<?php

declare(strict_types=1);

namespace SightPress\Core\Kernel\Infrastructure\Performance;

/**
 * Measures what the repository can measure on its own (shipped CSS and JavaScript),
 * adds measurements recorded elsewhere (a JSON file of budget name to value, written
 * by the browser and load-test jobs), and compares them all with the budget table.
 */
final readonly class BudgetCheck
{
    public function __construct(
        private BudgetTable $table,
        private AssetMeasurer $measurer = new AssetMeasurer(),
    ) {
    }

    /** @return array{passed: bool, lines: list<string>} */
    public function run(string $root, ?string $measurementsFile = null): array
    {
        $measurements = $this->measurer->measure($root);
        if (null !== $measurementsFile) {
            $measurements = [...$measurements, ...$this->readMeasurements($measurementsFile)];
        }

        $violations = new BudgetChecker($this->table)->violations($measurements);
        $count = \count($measurements);
        $summary = \sprintf(
            '%d %s checked against %d %s',
            $count,
            1 === $count ? 'measurement' : 'measurements',
            \count($this->table->budgets),
            1 === \count($this->table->budgets) ? 'budget' : 'budgets',
        );

        if ([] === $violations) {
            return ['passed' => true, 'lines' => ["Performance budgets: Passed ($summary)."]];
        }

        $broken = \count($violations);

        return ['passed' => false, 'lines' => [
            ...$violations,
            \sprintf('Performance budgets: Failed (%s, %d %s).', $summary, $broken, 1 === $broken ? 'problem' : 'problems'),
        ]];
    }

    /** @return array<string, int|float> */
    private function readMeasurements(string $file): array
    {
        $contents = is_file($file) ? file_get_contents($file) : false;
        if (false === $contents) {
            throw new \InvalidArgumentException(\sprintf('The measurements file "%s" can not be read.', $file));
        }

        $data = json_decode($contents, true);
        if (!\is_array($data)) {
            throw new \InvalidArgumentException(\sprintf('The measurements file "%s" is not a JSON object of budget names and numbers.', $file));
        }

        $measurements = [];
        foreach ($data as $name => $value) {
            if (!\is_string($name) || (!\is_int($value) && !\is_float($value))) {
                throw new \InvalidArgumentException(\sprintf('The measurements file "%s" has an entry that is not a budget name and a number.', $file));
            }
            $measurements[$name] = $value;
        }

        return $measurements;
    }
}
