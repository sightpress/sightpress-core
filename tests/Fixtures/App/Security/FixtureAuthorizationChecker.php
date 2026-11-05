<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Fixtures\App\Security;

use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/** Grants the attributes a test asks for, until the security bundle arrives in M1. */
final readonly class FixtureAuthorizationChecker implements AuthorizationCheckerInterface
{
    /** @param list<string> $granted */
    public function __construct(private array $granted)
    {
    }

    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        return \in_array($attribute, $this->granted, true);
    }
}
