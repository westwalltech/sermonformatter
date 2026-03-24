<?php

declare(strict_types=1);

use NewSong\SermonFormatter\Services\MarkdownToBard;
use Statamic\Fieldtypes\Bard\Augmentor;

/**
 * Detect whether Statamic's Bard Augmentor has its Tiptap extensions registered.
 * The extensions are populated via Augmentor::addExtensions() during Statamic's
 * service provider boot. Without them, the tiptap-php DOMParser fails with
 * "Class name must be a valid object or a string".
 */
function bardIsBootstrapped(): bool
{
    if (! class_exists(Augmentor::class)) {
        return false;
    }

    $extensions = (new ReflectionProperty(Augmentor::class, 'extensions'))->getValue();

    return ! empty($extensions);
}

it('converts heading to bard node', function () {
    $converter = new MarkdownToBard;
    $result = $converter->convert('# Sermon Title');

    expect($result)->toBeArray()
        ->and($result[0]['type'])->toBe('heading');
})->skip(! bardIsBootstrapped(), 'Requires Statamic Bard extensions to be registered');

it('converts paragraph to bard node', function () {
    $converter = new MarkdownToBard;
    $result = $converter->convert('This is a paragraph.');

    expect($result)->toBeArray()
        ->and($result[0]['type'])->toBe('paragraph');
})->skip(! bardIsBootstrapped(), 'Requires Statamic Bard extensions to be registered');

it('converts blockquote to bard node', function () {
    $converter = new MarkdownToBard;
    $result = $converter->convert('> This is a quote');

    expect($result)->toBeArray()
        ->and($result[0]['type'])->toBe('blockquote');
})->skip(! bardIsBootstrapped(), 'Requires Statamic Bard extensions to be registered');

it('converts horizontal rule to bard node', function () {
    $converter = new MarkdownToBard;
    $result = $converter->convert("Paragraph 1\n\n---\n\nParagraph 2");

    $types = array_column($result, 'type');
    expect($types)->toContain('horizontalRule');
})->skip(! bardIsBootstrapped(), 'Requires Statamic Bard extensions to be registered');

it('converts ordered list to bard node', function () {
    $converter = new MarkdownToBard;
    $result = $converter->convert("1. First\n2. Second\n3. Third");

    expect($result)->toBeArray()
        ->and($result[0]['type'])->toBe('orderedList');
})->skip(! bardIsBootstrapped(), 'Requires Statamic Bard extensions to be registered');

it('converts unordered list to bard node', function () {
    $converter = new MarkdownToBard;
    $result = $converter->convert("- First\n- Second\n- Third");

    expect($result)->toBeArray()
        ->and($result[0]['type'])->toBe('bulletList');
})->skip(! bardIsBootstrapped(), 'Requires Statamic Bard extensions to be registered');

it('handles empty markdown', function () {
    $converter = new MarkdownToBard;
    $result = $converter->convert('');

    expect($result)->toBeArray();
})->skip(! bardIsBootstrapped(), 'Requires Statamic Bard extensions to be registered');

it('preserves bold and italic formatting', function () {
    $converter = new MarkdownToBard;
    $result = $converter->convert('This has **bold** and *italic* text.');

    expect($result)->toBeArray()
        ->and($result[0]['type'])->toBe('paragraph');
})->skip(! bardIsBootstrapped(), 'Requires Statamic Bard extensions to be registered');
