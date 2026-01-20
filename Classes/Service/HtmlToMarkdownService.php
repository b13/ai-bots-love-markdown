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
    private HtmlConverter $converter;

    public function __construct()
    {
        $this->converter = new HtmlConverter([
            'strip_tags' => true,
            'hard_break' => true,
            'remove_nodes' => 'script style nav footer aside header form iframe',
        ]);
        $this->converter->getEnvironment()->addConverter(new TableConverter());
    }

    public function convert(string $html, string $baseUrl = ''): string
    {
        // Remove navigation, footer, aside elements
        $html = $this->removeNonContentElements($html);

        // Make image URLs absolute
        if ($baseUrl !== '') {
            $html = $this->makeUrlsAbsolute($html, $baseUrl);
        }

        $markdown = $this->converter->convert($html);

        // Clean up excessive whitespace
        $markdown = $this->cleanupMarkdown($markdown);

        return $markdown;
    }

    private function removeNonContentElements(string $html): string
    {
        // Remove elements that typically don't contain main content
        $patterns = [
            '/<nav\b[^>]*>.*?<\/nav>/is',
            '/<footer\b[^>]*>.*?<\/footer>/is',
            '/<aside\b[^>]*>.*?<\/aside>/is',
            '/<header\b[^>]*>.*?<\/header>/is',
            '/<form\b[^>]*>.*?<\/form>/is',
            '/<script\b[^>]*>.*?<\/script>/is',
            '/<style\b[^>]*>.*?<\/style>/is',
            '/<iframe\b[^>]*>.*?<\/iframe>/is',
            '/<noscript\b[^>]*>.*?<\/noscript>/is',
        ];

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
