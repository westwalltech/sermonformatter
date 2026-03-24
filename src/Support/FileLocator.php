<?php

declare(strict_types=1);

namespace NewSong\SermonFormatter\Support;

use Illuminate\Support\Facades\File;

class FileLocator
{
    public static function findUploadedFile(string $fileName): ?string
    {
        $uploadDir = storage_path('sermon-formatter/uploads');
        if (! File::isDirectory($uploadDir)) {
            return null;
        }

        // Look for the file (may have timestamp prefix)
        $files = File::glob($uploadDir.'/*_'.$fileName);
        if (! empty($files)) {
            return end($files); // Return the most recent one
        }

        // Also check for exact match
        $exactPath = $uploadDir.'/'.$fileName;
        if (File::exists($exactPath)) {
            return $exactPath;
        }

        return null;
    }
}
