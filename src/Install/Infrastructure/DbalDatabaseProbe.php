<?php

declare(strict_types=1);

namespace SightPress\Core\Install\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use SightPress\Core\Install\Domain\DatabaseCheck;
use SightPress\Core\Install\Domain\DatabaseParameters;
use SightPress\Core\Install\Domain\DatabaseProbe;

/** Talks to PostgreSQL through its maintenance database, so it works before the SightPress one exists. */
final readonly class DbalDatabaseProbe implements DatabaseProbe
{
    public function check(DatabaseParameters $parameters): DatabaseCheck
    {
        try {
            $connection = $this->maintenance($parameters);
            $exists = false !== $connection->fetchOne('SELECT 1 FROM pg_database WHERE datname = ?', [$parameters->name]);
            $connection->close();

            return new DatabaseCheck(true, $exists);
        } catch (\Throwable $e) {
            return new DatabaseCheck(false, false, $this->reason($e, $parameters));
        }
    }

    public function create(DatabaseParameters $parameters): ?string
    {
        try {
            $connection = $this->maintenance($parameters);
            $connection->executeStatement('CREATE DATABASE '.$connection->quoteSingleIdentifier($parameters->name));
            $connection->close();

            return null;
        } catch (\Throwable $e) {
            return $this->reason($e, $parameters);
        }
    }

    private function maintenance(DatabaseParameters $parameters): Connection
    {
        $url = new DatabaseParameters($parameters->host, $parameters->port, 'postgres', $parameters->user, $parameters->password)->url();
        $connection = DriverManager::getConnection(new DsnParser(['postgresql' => 'pdo_pgsql'])->parse($url));
        $connection->executeQuery('SELECT 1');

        return $connection;
    }

    /** The driver's message, without the password should a driver ever repeat it. */
    private function reason(\Throwable $e, DatabaseParameters $parameters): string
    {
        $message = trim((string) preg_replace('/\s+/', ' ', $e->getMessage()));
        if ('' !== $parameters->password) {
            $message = str_replace([$parameters->password, rawurlencode($parameters->password)], '[hidden]', $message);
        }

        return $message;
    }
}
