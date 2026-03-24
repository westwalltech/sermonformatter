<?php

declare(strict_types=1);

namespace NewSong\SermonFormatter\Services;

use Illuminate\Support\Facades\File;
use NewSong\SermonFormatter\Support\Logger;

class FormattingSpecs
{
    protected string $storagePath;

    public function __construct()
    {
        $this->storagePath = storage_path('sermon-formatter/formatting-specs.md');
    }

    public function get(): string
    {
        if (File::exists($this->storagePath)) {
            return File::get($this->storagePath);
        }

        return $this->getDefault();
    }

    public function save(string $specs): void
    {
        $directory = dirname($this->storagePath);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($this->storagePath, $specs);

        Logger::info('Formatting specs updated', [
            'length' => strlen($specs),
        ]);
    }

    public function reset(): void
    {
        if (File::exists($this->storagePath)) {
            File::delete($this->storagePath);
        }

        Logger::info('Formatting specs reset to defaults');
    }

    public function getDefault(): string
    {
        return <<<'SPECS'
You are a sermon notes formatter. Your job is to take raw, unformatted sermon notes and convert them into clean, well-structured Markdown.

## Formatting Rules

1. **Title**: The sermon title should be a level 1 heading (`# Title`)
2. **Scripture References**: Format as bold text, e.g., **John 3:16**
3. **Main Points**: Use level 2 headings (`## Point 1: ...`)
4. **Sub-points**: Use level 3 headings (`### Sub-point`)
5. **Bible Verses**: When a full verse is quoted, format as a blockquote:
   > "For God so loved the world..." — John 3:16 (NIV)
6. **Lists**: Use bullet points for lists of items
7. **Emphasis**: Use *italics* for emphasis, **bold** for key terms
8. **Paragraphs**: Separate paragraphs with blank lines
9. **Clean up**: Fix typos, standardize formatting, remove extra whitespace
10. **Preserve content**: Do not add, remove, or change the meaning of any content. Only reformat.

## Output

Return ONLY the formatted Markdown. Do not include any preamble, explanation, or wrapper. Just the formatted sermon notes.
SPECS;
    }

    public function buildSystemPrompt(): string
    {
        return $this->get();
    }
}
