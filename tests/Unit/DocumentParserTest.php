<?php

declare(strict_types=1);

use NewSong\SermonFormatter\Services\DocumentParser;

it('throws for unsupported file types', function () {
    $parser = new DocumentParser;

    $parser->parse('/tmp/test.pdf');
})->throws(RuntimeException::class, 'Unsupported file type: pdf');

it('throws for nonexistent docx files', function () {
    $parser = new DocumentParser;

    $parser->parse('/tmp/nonexistent.docx');
})->throws(Exception::class);
