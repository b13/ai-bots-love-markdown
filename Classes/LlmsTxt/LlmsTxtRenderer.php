<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown\LlmsTxt;

/**
 * Renders the llms.txt document (https://llmstxt.org/).
 */
final readonly class LlmsTxtRenderer
{
    /**
     * @param \Closure(int): string $urlForPage
     */
    public function render(string $title, string $description, LlmsTxtStructure $structure, \Closure $urlForPage): string
    {
        $blocks = ['# ' . $this->singleLine($title)];

        $description = $this->singleLine($description);
        if ($description !== '') {
            $blocks[] = '> ' . $description;
        }

        foreach ($structure->sections as $section) {
            $blocks[] = $this->renderSection($this->singleLine($section->title), $section->entries, $urlForPage);
        }
        $blocks[] = $this->renderSection('Optional', $structure->optional, $urlForPage);
        $blocks = array_values(array_filter($blocks, static fn(string $block): bool => $block !== ''));

        return implode("\n\n", $blocks) . "\n";
    }

    /**
     * Returns '' when no entry has a URL, so the section is omitted.
     *
     * @param list<LlmsTxtEntry> $entries
     * @param \Closure(int): string $urlForPage
     */
    private function renderSection(string $title, array $entries, \Closure $urlForPage): string
    {
        $lines = [];
        foreach ($entries as $entry) {
            // An empty URL means the link could not be generated: skip the entry.
            $url = $urlForPage($entry->uid);
            if ($url === '') {
                continue;
            }
            $line = '- [' . $this->escapeLinkText($this->singleLine($entry->title)) . '](' . $url . ')';
            $note = $this->singleLine($entry->note);
            if ($note !== '') {
                $line .= ': ' . $note;
            }
            $lines[] = $line;
        }

        if ($lines === []) {
            return '';
        }

        return '## ' . $title . "\n\n" . implode("\n", $lines);
    }

    private function singleLine(string $text): string
    {
        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }

    private function escapeLinkText(string $text): string
    {
        return str_replace(['\\', '[', ']'], ['\\\\', '\\[', '\\]'], $text);
    }
}
