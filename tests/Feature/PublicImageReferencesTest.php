<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Hostinger runs on Linux, where file names are case-sensitive: an image path that only matches with
 * different capitals works on Laragon (Windows) but shows a broken image on the hosted site.
 */
class PublicImageReferencesTest extends TestCase
{
    public function test_every_referenced_public_image_exists_with_the_exact_file_name(): void
    {
        $files = collect(File::allFiles(public_path('images')))
            ->map(fn ($file) => 'images/'.str_replace('\\', '/', $file->getRelativePathname()))
            ->all();
        $sources = collect([...File::allFiles(resource_path()), ...File::allFiles(app_path())])
            ->filter(fn ($file) => in_array($file->getExtension(), ['php', 'css', 'js'], true));

        $missing = [];
        foreach ($sources as $source) {
            preg_match_all('#images/[A-Za-z0-9_ ./-]+\.(?:png|jpe?g|svg|webp|gif)#', $source->getContents(), $matches);
            foreach ($matches[0] as $path) {
                if (! in_array($path, $files, true)) {
                    $missing[] = $path.' (in '.$source->getRelativePathname().')';
                }
            }
        }

        $this->assertSame([], $missing, 'Referenced images must match a file in public/images exactly, including capital letters.');
    }
}
