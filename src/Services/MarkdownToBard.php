<?php

declare(strict_types=1);

namespace NewSong\SermonFormatter\Services;

use League\CommonMark\CommonMarkConverter;
use NewSong\SermonFormatter\Support\Logger;
use Statamic\Fieldtypes\Bard;
use Statamic\Fieldtypes\Bard\Augmentor;

class MarkdownToBard
{
    public function convert(string $markdown): array
    {
        Logger::debug('Converting markdown to Bard format', [
            'markdown_length' => strlen($markdown),
        ]);

        // Step 1: Convert Markdown to HTML
        $html = $this->markdownToHtml($markdown);

        Logger::debug('HTML generated', [
            'html_length' => strlen($html),
        ]);

        // Step 2: Convert HTML to ProseMirror JSON (Bard format)
        $bardContent = $this->htmlToBard($html);

        Logger::info('Markdown converted to Bard format', [
            'markdown_length' => strlen($markdown),
            'bard_nodes' => count($bardContent),
        ]);

        return $bardContent;
    }

    protected function markdownToHtml(string $markdown): string
    {
        $converter = new CommonMarkConverter([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);

        return $converter->convert($markdown)->getContent();
    }

    protected function htmlToBard(string $html): array
    {
        $bard = new Bard;
        $augmentor = new Augmentor($bard);

        $document = $augmentor->renderHtmlToProsemirror($html);

        return $document['content'] ?? [];
    }
}
