<?php

declare(strict_types=1);

namespace SightPress\Core\Quality\PHPStan;

use Doctrine\ORM\Query\FilterCollection;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use SightPress\Core\Site\Infrastructure\Doctrine\SiteFilter;
use SightPress\Core\Site\Infrastructure\Doctrine\SiteFilterSwitch;

/**
 * Site isolation (CLAUDE.md rule 6): the SiteFilter may only be turned off on
 * purpose, in super-admin code paths. Calls to SiteFilterSwitch::runWithoutSiteFilter()
 * and to Doctrine's FilterCollection::disable()/suspend() for the site filter
 * are refused everywhere else.
 *
 * @implements Rule<MethodCall>
 */
final readonly class SiteFilterRule implements Rule
{
    public const string IDENTIFIER = 'sightpress.siteFilter';

    /** Namespaces where the site filter may be turned off. */
    private const array ALLOWED = [
        'SightPress\\Core\\Admin\\SuperAdmin\\',
        // The switch itself.
        'SightPress\\Core\\Site\\Infrastructure\\Doctrine\\',
        // Tests that prove the switch and site isolation.
        'SightPress\\Core\\Tests\\',
    ];

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier) {
            return [];
        }
        $method = $node->name->toLowerString();
        $calledOn = $scope->getType($node->var);

        $turnsOff = match (true) {
            'runwithoutsitefilter' === $method => new ObjectType(SiteFilterSwitch::class)->isSuperTypeOf($calledOn)->yes(),
            \in_array($method, ['disable', 'suspend'], true) => new ObjectType(FilterCollection::class)->isSuperTypeOf($calledOn)->yes() && $this->namesTheSiteFilter($node, $scope),
            default => false,
        };
        if (!$turnsOff || $this->isAllowed($scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'The site filter is turned off outside a super-admin code path. Only code in %s may do this, through SiteFilterSwitch::runWithoutSiteFilter() with a reason and an isolation test.',
                self::ALLOWED[0],
            ))->identifier(self::IDENTIFIER)->build(),
        ];
    }

    /** A filter name that isn't a known constant could be the site filter, so it counts. */
    private function namesTheSiteFilter(MethodCall $node, Scope $scope): bool
    {
        $argument = $node->getArgs()[0] ?? null;
        if (null === $argument) {
            return true;
        }
        $names = $scope->getType($argument->value)->getConstantStrings();
        if ([] === $names) {
            return true;
        }

        return array_any($names, static fn ($name) => SiteFilter::NAME === $name->getValue());
    }

    private function isAllowed(Scope $scope): bool
    {
        $namespace = ($scope->getNamespace() ?? '').'\\';

        return array_any(self::ALLOWED, static fn ($allowed) => str_starts_with($namespace, $allowed));
    }
}
