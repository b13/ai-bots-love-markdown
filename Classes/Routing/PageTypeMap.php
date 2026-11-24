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

use B13\AiBotsLoveMarkdown\LlmsTxt\LlmsTxtSettings;
use B13\AiBotsLoveMarkdown\MarkdownPageType;

/**
 * Builds the `map` of a PageType route enhancer for the Markdown and llms.txt
 * page types. Works on the raw site configuration (SiteConfigurationLoadedEvent),
 * where site settings may be stored flat ("a.b.c") or nested (a: b: c:).
 */
final class PageTypeMap
{
    public const DOT_MD = '.md';
    public const LLMS_TXT = 'llms.txt';

    /**
     * @param array<string, int|string> $map
     * @return array<string, int|string>
     */
    public static function apply(array $map, array $siteSettings): array
    {
        $suffix = (string)self::setting($siteSettings, 'ai_bots_love_markdown.pageTypeSuffix', MarkdownPageType::SUFFIX);
        $markdownTypeNum = (int)self::setting($siteSettings, 'ai_bots_love_markdown.pageTypeTypeNum', MarkdownPageType::TYPE_NUM);

        if (!self::isLlmsTxtEnabled($siteSettings)) {
            if (!isset($map[$suffix]) && !in_array($markdownTypeNum, $map, true)) {
                $map[$suffix] = $markdownTypeNum;
            }
            return $map;
        }

        // TYPO3 generates links with the FIRST map key matching the type, and
        // keys starting with "." are appended to the page path (/page.md).
        $result = [self::DOT_MD => $markdownTypeNum];
        foreach ($map as $key => $value) {
            if ($key !== self::DOT_MD) {
                $result[$key] = $value;
            }
        }
        // Keep the legacy suffix so already known URLs keep resolving.
        $result[$suffix] ??= $markdownTypeNum;
        $result[self::LLMS_TXT] ??= (int)self::setting(
            $siteSettings,
            'ai_bots_love_markdown.llmsTxt.typeNum',
            LlmsTxtSettings::DEFAULT_TYPE_NUM,
        );

        return $result;
    }

    public static function isLlmsTxtEnabled(array $siteSettings): bool
    {
        $value = self::setting($siteSettings, 'ai_bots_love_markdown.llmsTxt.enabled', false);
        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool)$value;
    }

    public static function setting(array $siteSettings, string $dottedKey, mixed $default): mixed
    {
        if (array_key_exists($dottedKey, $siteSettings)) {
            return $siteSettings[$dottedKey];
        }

        $current = $siteSettings;
        foreach (explode('.', $dottedKey) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }
}
