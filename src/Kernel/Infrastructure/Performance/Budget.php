<?php

declare(strict_types=1);

namespace SightPress\Core\Kernel\Infrastructure\Performance;

/** One performance budget: a named bound and how a measurement is compared with it. */
final readonly class Budget
{
    /** @param 'lt'|'lte' $rule */
    public function __construct(
        public string $name,
        public int|float $limit,
        public string $rule,
        public string $description,
    ) {
    }

    public function isMet(int|float $measured): bool
    {
        return 'lte' === $this->rule ? $measured <= $this->limit : $measured < $this->limit;
    }
}
