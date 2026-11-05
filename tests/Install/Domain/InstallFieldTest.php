<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Install\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SightPress\Core\Install\Domain\InstallField;

final class InstallFieldTest extends TestCase
{
    public function testFileEnvAddsTheFileSuffix(): void
    {
        self::assertSame('SIGHTPRESS_DB_PASSWORD_FILE', new InstallField('db_password', 'Database password', 'SIGHTPRESS_DB_PASSWORD')->fileEnv());
    }

    /** @return iterable<string, array{InstallField, string}> */
    public static function validValues(): iterable
    {
        yield 'port' => [new InstallField('p', 'Port', 'P', type: InstallField::PORT), '5432'];
        yield 'lowest port' => [new InstallField('p', 'Port', 'P', type: InstallField::PORT), '1'];
        yield 'highest port' => [new InstallField('p', 'Port', 'P', type: InstallField::PORT), '65535'];
        yield 'email' => [new InstallField('e', 'Sender', 'E', type: InstallField::EMAIL), 'no-reply@example.org'];
        yield 'dsn' => [new InstallField('d', 'Mailer DSN', 'D', type: InstallField::DSN), 'smtp://localhost:1025'];
        yield 'host name' => [new InstallField('h', 'Host', 'H', type: InstallField::HOST), 'db.example.org'];
        yield 'ipv6 host' => [new InstallField('h', 'Host', 'H', type: InstallField::HOST), '[::1]'];
        yield 'identifier' => [new InstallField('i', 'Name', 'I', type: InstallField::IDENTIFIER), 'sight_press-1.0'];
        yield 'choice' => [new InstallField('c', 'Env', 'C', type: InstallField::CHOICE, choices: ['prod', 'dev']), 'dev'];
        yield 'text' => [new InstallField('t', 'Text', 'T'), 'anything'];
        yield 'path' => [new InstallField('t', 'Folder', 'T', type: InstallField::PATH), 'var/storage/media'];
    }

    #[DataProvider('validValues')]
    public function testValidValuesPass(InstallField $field, string $value): void
    {
        self::assertNull($field->validate($value));
    }

    /** @return iterable<string, array{InstallField, string, string}> */
    public static function invalidValues(): iterable
    {
        yield 'port with letters' => [new InstallField('p', 'Database port', 'P', type: InstallField::PORT, example: '5432'), 'abc', 'Database port: "abc" is not a port number. Enter a whole number from 1 to 65535 such as 5432.'];
        yield 'port zero' => [new InstallField('p', 'Database port', 'P', type: InstallField::PORT), '0', 'Database port: "0" is not a port number. Enter a whole number from 1 to 65535.'];
        yield 'port too high' => [new InstallField('p', 'Database port', 'P', type: InstallField::PORT), '65536', 'Database port: "65536" is not a port number. Enter a whole number from 1 to 65535.'];
        yield 'email' => [new InstallField('e', 'Sender', 'E', type: InstallField::EMAIL), 'nobody', 'Sender: "nobody" is not an email address. Enter one in the form name@example.org.'];
        yield 'dsn' => [new InstallField('d', 'Mailer DSN', 'D', type: InstallField::DSN, example: 'smtp://localhost:1025'), 'localhost', 'Mailer DSN: "localhost" is not a DSN. It must look like such as smtp://localhost:1025.'];
        yield 'host' => [new InstallField('h', 'Database host', 'H', type: InstallField::HOST), 'db host', 'Database host: "db host" is not a host name or address. Use letters, digits, dots and hyphens.'];
        yield 'identifier' => [new InstallField('i', 'Database name', 'I', type: InstallField::IDENTIFIER), 'a;b', 'Database name: "a;b" can only contain letters, digits, underscores, dots and hyphens.'];
        yield 'choice' => [new InstallField('c', 'Environment', 'C', type: InstallField::CHOICE, choices: ['prod', 'dev']), 'test', 'Environment: "test" is not one of the choices. Enter one of: prod, dev.'];
        yield 'empty text' => [new InstallField('t', 'Secret', 'T', example: 'abc'), '  ', 'Secret: no value was given. Enter a value such as abc.'];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValuesAreExplainedInWords(InstallField $field, string $value, string $expected): void
    {
        self::assertSame($expected, $field->validate($value));
    }

    public function testASecretValueIsNeverRepeatedInTheError(): void
    {
        $field = new InstallField('d', 'Mailer DSN', 'D', secret: true, type: InstallField::DSN);

        $error = $field->validate('hunter2-not-a-dsn');

        self::assertNotNull($error);
        self::assertStringNotContainsString('hunter2', $error);
        self::assertStringStartsWith('Mailer DSN: That value is not a DSN.', $error);
    }
}
