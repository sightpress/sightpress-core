<?php

declare(strict_types=1);

namespace SightPress\Core\Media\Application;

/**
 * Validates and prepares local media storage. The installer, the CLI and the
 * admin all go through this handler, so the rules can't differ. Saving the
 * MediaStorage row waits for the Media milestone.
 */
final readonly class ConfigureMediaStorageHandler
{
    public function __construct(private string $projectDir)
    {
    }

    public function __invoke(ConfigureMediaStorage $command): MediaStorageResult
    {
        if (ConfigureMediaStorage::LOCAL !== $command->type) {
            throw new InvalidMediaStorage('media storage', \sprintf('"%s" is not available yet. Choose "local" for storage on this server.', $command->type));
        }

        $path = trim($command->path);
        if ('' === $path) {
            throw new InvalidMediaStorage('media path', 'The media path is empty. Enter a folder such as var/storage/media.');
        }

        $absolute = str_starts_with($path, '/') ? $path : $this->projectDir.'/'.$path;
        $normalized = $this->normalize($absolute);
        $public = $this->normalize($this->projectDir.'/public');
        if ($normalized === $public || str_starts_with($normalized.'/', $public.'/')) {
            throw new InvalidMediaStorage('media path', 'The media path is inside public/, where the web server could run uploaded files. Choose a folder outside it, such as var/storage/media.');
        }

        $created = false;
        if (!is_dir($normalized)) {
            if (!@mkdir($normalized, 0o750, true) && !is_dir($normalized)) {
                throw new InvalidMediaStorage('media path', \sprintf('The folder %s can not be created. Check its parent folder is writable, or choose another path.', $normalized));
            }
            $created = true;
        }
        if (!is_writable($normalized)) {
            throw new InvalidMediaStorage('media path', \sprintf('The folder %s is not writable. Change its permissions or choose another path.', $normalized));
        }

        return new MediaStorageResult($command->type, $normalized, $created);
    }

    private function normalize(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ('' === $part || '.' === $part) {
                continue;
            }
            if ('..' === $part) {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return '/'.implode('/', $parts);
    }
}
