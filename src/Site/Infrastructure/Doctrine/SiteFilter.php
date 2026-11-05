<?php

declare(strict_types=1);

namespace SightPress\Core\Site\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use SightPress\Plugin\Core\Value\SiteOwnedInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Restricts every query on a site-owned entity to `site_id = :current`.
 * It fails closed: with no current site set, a query on a site-owned
 * entity throws instead of returning every site's rows.
 */
final class SiteFilter extends SQLFilter
{
    public const string NAME = 'sightpress_site';
    public const string PARAMETER = 'current';

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        $reflection = $targetEntity->getReflectionClass();
        if (!$reflection->implementsInterface(SiteOwnedInterface::class)) {
            return '';
        }

        if (!$this->hasParameter(self::PARAMETER)) {
            throw new \LogicException(\sprintf('A query on the site-owned entity %s ran with no current site. Resolve the site first, or run it through SiteFilterSwitch in a super-admin code path.', $targetEntity->getName()));
        }

        $siteId = trim($this->getParameter(self::PARAMETER), "'");
        if (!Ulid::isValid($siteId)) {
            throw new \LogicException('The current site id is not a valid ULID.');
        }

        $connection = $this->getConnection();
        $stored = $connection->convertToDatabaseValue(Ulid::fromString($siteId), 'ulid');
        if (!\is_string($stored)) {
            throw new \LogicException('The database platform returned a site id that is not a string.');
        }

        return \sprintf('%s.site_id = %s', $targetTableAlias, self::literal($connection, $stored));
    }

    /**
     * Platforms with a native UUID type get a quoted string. Others store
     * ULIDs as 16 raw bytes, written as a hex literal. SQLite keeps those
     * bytes as text when the type binds them as a string, and text never
     * equals a blob there, so the literal is cast to match how rows are written.
     */
    private static function literal(Connection $connection, string $stored): string
    {
        if (16 !== \strlen($stored)) {
            return $connection->quote($stored);
        }

        $hex = "X'".bin2hex($stored)."'";
        $bindsAsString = ParameterType::STRING === Type::getType('ulid')->getBindingType();

        return $bindsAsString && $connection->getDatabasePlatform() instanceof SQLitePlatform
            ? 'CAST('.$hex.' AS TEXT)'
            : $hex;
    }
}
