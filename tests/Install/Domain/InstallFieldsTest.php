<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Install\Domain;

use PHPUnit\Framework\TestCase;
use SightPress\Core\Install\Domain\InstallFields;

final class InstallFieldsTest extends TestCase
{
    public function testEveryFieldIsKeyedAndHasASightPressVariable(): void
    {
        $fields = InstallFields::all();

        self::assertCount(13, $fields);
        foreach ($fields as $key => $field) {
            self::assertSame($key, $field->key);
            self::assertStringStartsWith('SIGHTPRESS_', $field->env);
        }
    }

    public function testSecretsHaveNoCommandLineOption(): void
    {
        foreach (InstallFields::all() as $field) {
            if ($field->secret) {
                self::assertNull($field->option, $field->key.' is a secret, so it must come only from the environment or a file.');
            }
        }
    }

    public function testGetReturnsOneField(): void
    {
        self::assertSame('5432', InstallFields::get(InstallFields::DB_PORT)->default);
    }

    public function testGetRefusesAnUnknownField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown installer field "nope".');

        InstallFields::get('nope');
    }
}
