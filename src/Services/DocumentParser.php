<?php

declare(strict_types=1);

namespace NewSong\SermonFormatter\Services;

use NewSong\SermonFormatter\Support\Logger;
use PhpOffice\PhpWord\IOFactory;

class DocumentParser
{
    public function parse(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'docx' => $this->parseDocx($filePath),
            'rtf' => $this->parseRtf($filePath),
            default => throw new \RuntimeException("Unsupported file type: {$extension}"),
        };
    }

    protected function parseDocx(string $filePath): string
    {
        Logger::debug('Parsing DOCX file', ['path' => basename($filePath)]);

        $phpWord = IOFactory::load($filePath, 'Word2007');
        $text = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $text .= $this->extractText($element)."\n";
            }
        }

        $text = trim($text);

        Logger::info('DOCX parsed successfully', [
            'file' => basename($filePath),
            'characters' => strlen($text),
        ]);

        return $text;
    }

    protected function parseRtf(string $filePath): string
    {
        Logger::debug('Parsing RTF file', ['path' => basename($filePath)]);

        // Try PHPWord RTF reader first
        try {
            $phpWord = IOFactory::load($filePath, 'RTF');
            $text = '';

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    $text .= $this->extractText($element)."\n";
                }
            }

            $text = trim($text);

            if (! empty($text)) {
                Logger::info('RTF parsed via PHPWord', [
                    'file' => basename($filePath),
                    'characters' => strlen($text),
                ]);

                return $text;
            }
        } catch (\Exception $e) {
            Logger::warning('PHPWord RTF parsing failed, trying fallback', [
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback: strip RTF control codes
        $content = file_get_contents($filePath);
        $text = $this->stripRtf($content);

        Logger::info('RTF parsed via fallback', [
            'file' => basename($filePath),
            'characters' => strlen($text),
        ]);

        return $text;
    }

    protected function extractText($element): string
    {
        $text = '';

        if (method_exists($element, 'getText')) {
            $elementText = $element->getText();
            if (is_string($elementText)) {
                return $elementText;
            }
        }

        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                $text .= $this->extractText($child);
            }
        }

        return $text;
    }

    protected function stripRtf(string $rtf): string
    {
        // Remove RTF headers and footers
        $rtf = preg_replace('/\{\\\\rtf[^}]*\}$/s', '', $rtf);

        // Remove RTF control words
        $rtf = preg_replace('/\\\\[a-z]+[-]?\d*\s?/i', '', $rtf);

        // Remove RTF groups
        $rtf = preg_replace('/[{}]/', '', $rtf);

        // Remove special characters
        $rtf = preg_replace("/\\\\'/", '', $rtf);

        // Clean up whitespace
        $rtf = preg_replace('/\n{3,}/', "\n\n", $rtf);

        return trim($rtf);
    }
}
