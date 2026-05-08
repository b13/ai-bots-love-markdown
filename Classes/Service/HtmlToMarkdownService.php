<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown\Service;

use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\HtmlConverter;

class HtmlToMarkdownService
{
    /**
     * Default tags stripped from the markdown output. <header> is intentionally
     * NOT in this list: an article-level <header> often contains the page H1
     * and should reach the markdown output. Page-level <header> regions can be
     * excluded via the MarkdownExclude partial (or by selecting <main> /
     * markdown-start markers that exclude the page header by structure).
     */
    public const DEFAULT_REMOVE_ELEMENTS = 'script style nav footer aside form iframe noscript';

    public function convert(string $html, string $baseUrl = '', ?string $removeElements = null): string
    {
        $elements = $this->parseElementList($removeElements ?? self::DEFAULT_REMOVE_ELEMENTS);

        $html = $this->removeNonContentElements($html, $elements);

        if ($baseUrl !== '') {
            $html = $this->makeUrlsAbsolute($html, $baseUrl);
        }

        $markdown = $this->buildConverter($elements)->convert($html);

        return $this->cleanupMarkdown($markdown);
    }

    private function buildConverter(array $elements): HtmlConverter
    {
        $converter = new HtmlConverter([
            'strip_tags' => true,
            'hard_break' => true,
            'remove_nodes' => implode(' ', $elements),
        ]);
        $converter->getEnvironment()->addConverter(new TableConverter());
        return $converter;
    }

    /**
     * @return string[]
     */
    private function parseElementList(string $list): array
    {
        $elements = preg_split('/\s+/', trim($list)) ?: [];
        return array_values(array_filter($elements, static fn(string $tag) => $tag !== ''));
    }

    /**
     * @param string[] $elements
     */
    private function removeNonContentElements(string $html, array $elements): string
    {
        if ($elements === []) {
            return $html;
        }
        $patterns = array_map(
            static fn(string $tag) => '/<' . preg_quote($tag, '/') . '\b[^>]*>.*?<\/' . preg_quote($tag, '/') . '>/is',
            $elements,
        );
        return preg_replace($patterns, '', $html) ?? $html;
    }

    private function makeUrlsAbsolute(string $html, string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');

        // Make src attributes absolute
        $html = preg_replace_callback(
            '/(<img[^>]+src=")([^"]+)(")/i',
            function ($matches) use ($baseUrl) {
                $url = $matches[2];
                if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://') && !str_starts_with($url, '//')) {
                    $url = $baseUrl . '/' . ltrim($url, '/');
                }
                return $matches[1] . $url . $matches[3];
            },
            $html
        );

        // Make href attributes absolute
        $html = preg_replace_callback(
            '/(<a[^>]+href=")([^"]+)(")/i',
            function ($matches) use ($baseUrl) {
                $url = $matches[2];
                if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://') && !str_starts_with($url, '//') && !str_starts_with($url, '#') && !str_starts_with($url, 'mailto:') && !str_starts_with($url, 'tel:')) {
                    $url = $baseUrl . '/' . ltrim($url, '/');
                }
                return $matches[1] . $url . $matches[3];
            },
            $html
        );

        return $html ?? '';
    }

    private function cleanupMarkdown(string $markdown): string
    {
        // Trim whitespace from each line
        $lines = explode("\n", $markdown);
        $lines = array_map('rtrim', $lines);
        $markdown = implode("\n", $lines);
        $markdown = htmlspecialchars_decode($markdown);
        // Remove excessive blank lines (more than 2 consecutive)
        $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown) ?? $markdown;

        return trim($markdown);
    }
}
