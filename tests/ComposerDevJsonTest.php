<?php

declare(strict_types=1);

namespace SightPress\Core\Tests;

use PHPUnit\Framework\TestCase;

/**
 * composer.dev.json (sibling checkouts, plugin CI) must install what
 * composer.json installs. It only adds the path repository and accepts the
 * development version of plugin-core.
 */
final class ComposerDevJsonTest extends TestCase
{
    public function testTheDevelopmentSetUpMatchesThePackage(): void
    {
        $package = $this->read('composer.json');
        $development = $this->read('composer.dev.json');

        self::assertIsArray($development['repositories'] ?? null);
        unset($development['repositories']);
        self::assertIsArray($package['require'] ?? null);
        self::assertIsArray($development['require'] ?? null);
        $constraint = $package['require']['sightpress/plugin-core'] ?? null;
        self::assertIsString($constraint);
        self::assertSame($constraint.'@dev', $development['require']['sightpress/plugin-core'] ?? null);
        unset($package['require']['sightpress/plugin-core'], $development['require']['sightpress/plugin-core']);

        self::assertSame($package, $development, 'Copy the change from composer.json to composer.dev.json.');
    }

    /** @return array<mixed> */
    private function read(string $file): array
    {
        $data = json_decode((string) file_get_contents(\dirname(__DIR__).'/'.$file), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
