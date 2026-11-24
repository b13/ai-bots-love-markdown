<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown\Routing;

/**
 * Recognises Markdown URLs and returns the path of the underlying page.
 *
 * Supported forms (full paths with leading slash, or route tails without):
 *  - legacy suffix segment: /page/ai-bots-love.md → /page
 *  - ".md" appended to the page path (llms.txt enabled): /page.md → /page,
 *    /index.md → /, /en/index.md → /en
 */
final class MarkdownPath
{
    private const DOT_MD = '.md';
    private const INDEX = 'index';

    /**
     * @return string|null cleaned path, or null if $path is not a Markdown URL
     */
    public static function strip(string $path, string $legacySuffix, bool $dotMdEnabled): ?string
    {
        $isFullPath = str_starts_with($path, '/');

        if ($legacySuffix !== '' && ($path === $legacySuffix || str_ends_with($path, '/' . $legacySuffix))) {
            $base = substr($path, 0, -strlen($legacySuffix));
            $base = rtrim($base, '/');
            return ($base === '' && $isFullPath) ? '/' : $base;
        }

        if (!$dotMdEnabled || !str_ends_with($path, self::DOT_MD)) {
            return null;
        }

        $base = substr($path, 0, -strlen(self::DOT_MD));
        if ($base === self::INDEX || str_ends_with($base, '/' . self::INDEX)) {
            $base = substr($base, 0, -strlen(self::INDEX));
        }
        // Same as the legacy suffix: no trailing slash (/en/index.md → /en),
        // so trailing-slash redirects don't drop the Markdown request.
        $base = rtrim($base, '/');

        return ($base === '' && $isFullPath) ? '/' : $base;
    }
}
