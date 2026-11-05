<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Media\Application;

use PHPUnit\Framework\TestCase;
use SightPress\Core\Media\Application\ConfigureMediaStorage;
use SightPress\Core\Media\Application\ConfigureMediaStorageHandler;
use SightPress\Core\Media\Application\InvalidMediaStorage;

final class ConfigureMediaStorageHandlerTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir().'/sightpress-media-'.bin2hex(random_bytes(6));
        mkdir($this->project.'/public', 0o777, true);
    }

    protected function tearDown(): void
    {
        chmod($this->project, 0o777);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->project, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            \assert($file instanceof \SplFileInfo);
            chmod($file->getPathname(), 0o777);
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->project);
    }

    private function configure(string $path, string $type = ConfigureMediaStorage::LOCAL): \SightPress\Core\Media\Application\MediaStorageResult
    {
        return new ConfigureMediaStorageHandler($this->project)(new ConfigureMediaStorage($type, $path));
    }

    private function expectInvalid(string $field, string $message, string $path, string $type = ConfigureMediaStorage::LOCAL): void
    {
        try {
            $this->configure($path, $type);
            self::fail('The media storage was accepted.');
        } catch (InvalidMediaStorage $e) {
            self::assertSame($field, $e->field);
            self::assertStringContainsString($message, $e->getMessage());
        }
    }

    public function testARelativePathIsCreatedInsideTheProject(): void
    {
        $result = $this->configure('var/storage/media');

        self::assertSame('local', $result->type);
        self::assertSame($this->project.'/var/storage/media', $result->absolutePath);
        self::assertTrue($result->created);
        self::assertDirectoryExists($result->absolutePath);
    }

    public function testAnExistingFolderIsUsedAsItIs(): void
    {
        mkdir($this->project.'/media');

        $result = $this->configure($this->project.'/./media/');

        self::assertSame($this->project.'/media', $result->absolutePath);
        self::assertFalse($result->created);
    }

    public function testOnlyLocalStorageIsAvailable(): void
    {
        $this->expectInvalid('media storage', '"s3" is not available yet. Choose "local"', 'var/media', 's3');
    }

    public function testAnEmptyPathIsRefused(): void
    {
        $this->expectInvalid('media path', 'The media path is empty.', '   ');
    }

    public function testAPathInsidePublicIsRefused(): void
    {
        $this->expectInvalid('media path', 'inside public/', 'public/uploads');
        $this->expectInvalid('media path', 'inside public/', 'public');
        $this->expectInvalid('media path', 'inside public/', 'var/../public/media');
    }

    public function testAFolderNamedLikePublicIsAllowed(): void
    {
        self::assertSame($this->project.'/publications', $this->configure('publications')->absolutePath);
    }

    public function testAFolderThatCanNotBeCreatedIsExplained(): void
    {
        mkdir($this->project.'/locked');
        chmod($this->project.'/locked', 0o500);

        $this->expectInvalid('media path', 'can not be created', 'locked/media');
    }

    public function testAFolderThatIsNotWritableIsExplained(): void
    {
        mkdir($this->project.'/readonly');
        chmod($this->project.'/readonly', 0o500);

        $this->expectInvalid('media path', 'is not writable', 'readonly');
    }
}
