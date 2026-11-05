<?php

declare(strict_types=1);

namespace SightPress\Core\Kernel\Infrastructure\Performance;

/** Compares measurements with the budget table and reports every broken budget in words. */
final readonly class BudgetChecker
{
    public function __construct(private BudgetTable $table)
    {
    }

    /**
     * @param array<string, int|float> $measurements keyed by budget name
     *
     * @return list<string>
     */
    public function violations(array $measurements): array
    {
        $violations = [];
        foreach ($measurements as $name => $measured) {
            $budget = $this->table->budgets[$name] ?? null;
            if (null === $budget) {
                $violations[] = \sprintf('"%s" has no budget. Add it to config/budgets.yaml or correct the name.', $name);
                continue;
            }
            if (!$budget->isMet($measured)) {
                $violations[] = \sprintf(
                    '%s: measured %s, but the budget is %s %s.',
                    $budget->description,
                    $measured,
                    'lte' === $budget->rule ? 'at most' : 'under',
                    $budget->limit,
                );
            }
        }

        return $violations;
    }
}
