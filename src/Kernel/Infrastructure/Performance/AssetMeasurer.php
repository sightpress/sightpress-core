<?php

declare(strict_types=1);

namespace SightPress\Core\Kernel\Infrastructure\Performance;

/** Measures the compressed size of the theme CSS and JavaScript the repository ships. */
final class AssetMeasurer
{
    /**
     * @return array<string, int>
     */
    public function measure(string $root): array
    {
        $measurements = [];
        $css = $this->compressedSize($root.'/themes', 'css');
        if (null !== $css) {
            $measurements['assets.theme_css_compressed_bytes'] = $css;
        }
        $js = $this->compressedSize($root.'/themes', 'js');
        if (null !== $js) {
            $measurements['assets.public_js_compressed_bytes'] = $js;
        }

        return $measurements;
    }

    private function compressedSize(string $directory, string $extension): ?int
    {
        if (!is_dir($directory)) {
            return null;
        }

        $total = 0;
        $found = false;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $extension !== $file->getExtension()) {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            $compressed = false === $contents ? false : gzencode($contents, 9);
            $total += false === $compressed ? 0 : \strlen($compressed);
            $found = true;
        }

        return $found ? $total : null;
    }
}
