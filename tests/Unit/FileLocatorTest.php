<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use NewSong\SermonFormatter\Support\FileLocator;

beforeEach(function () {
    $this->uploadDir = storage_path('sermon-formatter/uploads');
    if (! File::isDirectory($this->uploadDir)) {
        File::makeDirectory($this->uploadDir, 0755, true);
    }
});

afterEach(function () {
    // Clean up all test files
    foreach (File::glob($this->uploadDir.'/*_test_sermon.docx') as $file) {
        File::delete($file);
    }
    @File::delete($this->uploadDir.'/exact_match.docx');
});

it('finds file with timestamp prefix', function () {
    $filePath = $this->uploadDir.'/1711234567_test_sermon.docx';
    file_put_contents($filePath, 'content');

    $found = FileLocator::findUploadedFile('test_sermon.docx');

    expect($found)->toBe($filePath);
});

it('returns most recent file when multiple exist', function () {
    $older = $this->uploadDir.'/1000000000_test_sermon.docx';
    $newer = $this->uploadDir.'/2000000000_test_sermon.docx';
    file_put_contents($older, 'old');
    file_put_contents($newer, 'new');

    $found = FileLocator::findUploadedFile('test_sermon.docx');

    expect($found)->toBe($newer);
});

it('falls back to exact match', function () {
    $filePath = $this->uploadDir.'/exact_match.docx';
    file_put_contents($filePath, 'content');

    $found = FileLocator::findUploadedFile('exact_match.docx');

    expect($found)->toBe($filePath);
});

it('returns null when file not found', function () {
    $found = FileLocator::findUploadedFile('nonexistent.docx');

    expect($found)->toBeNull();
});
