<?php

declare(strict_types=1);

namespace App\Modules\Content\Services;

/**
 * Turns the store page plain-text body into structured blocks for the storefront.
 *
 * Supported markup: "## Heading", "- bullet item", blank line between paragraphs.
 * Output is plain strings only, so the Vue page renders it with text interpolation (no v-html / XSS).
 */
final class StorePageBodyParser
{
    /**
     * @return list<array{type: 'heading'|'paragraph', text: string}|array{type: 'list', items: list<string>}>
     */
    public function parse(string $body): array
    {
        $blocks = [];
        $paragraph = [];
        $listItems = [];

        $flushParagraph = function () use (&$blocks, &$paragraph): void {
            if ($paragraph !== []) {
                $blocks[] = ['type' => 'paragraph', 'text' => implode(' ', $paragraph)];
                $paragraph = [];
            }
        };

        $flushList = function () use (&$blocks, &$listItems): void {
            if ($listItems !== []) {
                $blocks[] = ['type' => 'list', 'items' => $listItems];
                $listItems = [];
            }
        };

        foreach (preg_split('/\R/u', $body) ?: [] as $rawLine) {
            $line = trim($rawLine);

            if ($line === '') {
                $flushParagraph();
                $flushList();

                continue;
            }

            if (str_starts_with($line, '## ')) {
                $flushParagraph();
                $flushList();
                $blocks[] = ['type' => 'heading', 'text' => trim(substr($line, 3))];

                continue;
            }

            if (str_starts_with($line, '- ')) {
                $flushParagraph();
                $listItems[] = trim(substr($line, 2));

                continue;
            }

            $flushList();
            $paragraph[] = $line;
        }

        $flushParagraph();
        $flushList();

        return $blocks;
    }
}
