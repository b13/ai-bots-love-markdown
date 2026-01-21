<?php

declare(strict_types=1);

namespace B13\AiBotsLoveMarkdown\Service;

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class MetadataService
{
    public function __construct(
        protected ConnectionPool $connectionPool,
    ) {}

    /**
     * Generate YAML front matter from HTML meta tags and page record
     * Meta tags take priority, page record is used as fallback
     */
    public function generateFrontMatter(string $html, array $pageRecord, string $fallbackUrl): string
    {
        // Extract metadata from HTML meta tags
        $metaTags = $this->extractMetaTags($html);

        $frontMatter = [];

        // Title: og:title > meta title > page record
        $frontMatter['title'] = $metaTags['og:title']
            ?? $metaTags['title']
            ?? $pageRecord['title']
            ?? '';

        // URL: canonical > fallback URL
        $frontMatter['url'] = $metaTags['canonical'] ?? $fallbackUrl;

        // Description: og:description > meta description > page record
        $description = $metaTags['og:description']
            ?? $metaTags['description']
            ?? $pageRecord['description']
            ?? '';
        if ($description !== '') {
            $frontMatter['description'] = $description;
        }

        // Image: og:image
        if (!empty($metaTags['og:image'])) {
            $frontMatter['image'] = $metaTags['og:image'];
        }

        // Author: meta author > page record
        $author = $metaTags['author'] ?? $pageRecord['author'] ?? '';
        if ($author !== '') {
            $frontMatter['author'] = $author;
        }

        // Dates from page record (no standard meta tags for these)
        if (!empty($pageRecord['crdate'])) {
            $frontMatter['date'] = date('Y-m-d', (int)$pageRecord['crdate']);
        }
        if (!empty($pageRecord['tstamp'])) {
            $frontMatter['modified'] = date('Y-m-d', (int)$pageRecord['tstamp']);
        }
        if (!empty($pageRecord['SYS_LASTCHANGED'])) {
            $frontMatter['lastUpdated'] = date('Y-m-d', (int)$pageRecord['SYS_LASTCHANGED']);
        }

        // Keywords from meta tags
        if (!empty($metaTags['keywords'])) {
            $keywords = array_map('trim', explode(',', $metaTags['keywords']));
            $keywords = array_filter($keywords);
            if ($keywords !== []) {
                $frontMatter['keywords'] = $keywords;
            }
        }

        // Categories from database
        $categories = $this->getPageCategories((int)($pageRecord['uid'] ?? 0));
        if ($categories !== []) {
            $frontMatter['categories'] = $categories;
        }

        // Type: og:type
        if (!empty($metaTags['og:type'])) {
            $frontMatter['type'] = $metaTags['og:type'];
        }

        // Site name: og:site_name
        if (!empty($metaTags['og:site_name'])) {
            $frontMatter['site'] = $metaTags['og:site_name'];
        }

        // Locale: og:locale
        if (!empty($metaTags['og:locale'])) {
            $frontMatter['locale'] = $metaTags['og:locale'];
        }

        return $this->formatYamlFrontMatter($frontMatter);
    }

    /**
     * Extract meta tags from HTML
     */
    protected function extractMetaTags(string $html): array
    {
        $meta = [];

        // Extract <title> tag
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
            $meta['title'] = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        // Extract canonical URL
        if (preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            $meta['canonical'] = $matches[1];
        } elseif (preg_match('/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\']canonical["\'][^>]*>/i', $html, $matches)) {
            $meta['canonical'] = $matches[1];
        }

        // Extract meta name tags (description, keywords, author)
        preg_match_all('/<meta[^>]+name=["\']([^"\']+)["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $name = strtolower($match[1]);
            if (in_array($name, ['description', 'keywords', 'author'], true)) {
                $meta[$name] = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        // Also check reverse order (content before name)
        preg_match_all('/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']([^"\']+)["\'][^>]*>/i', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $name = strtolower($match[2]);
            if (in_array($name, ['description', 'keywords', 'author'], true) && !isset($meta[$name])) {
                $meta[$name] = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        // Extract Open Graph meta tags
        preg_match_all('/<meta[^>]+property=["\']([^"\']+)["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $property = strtolower($match[1]);
            if (str_starts_with($property, 'og:')) {
                $meta[$property] = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        // Also check reverse order (content before property)
        preg_match_all('/<meta[^>]+content=["\']([^"\']*)["\'][^>]+property=["\']([^"\']+)["\'][^>]*>/i', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $property = strtolower($match[2]);
            if (str_starts_with($property, 'og:') && !isset($meta[$property])) {
                $meta[$property] = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $meta;
    }

    protected function getPageCategories(int $pageUid): array
    {
        if ($pageUid === 0) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_category');
        $result = $queryBuilder
            ->select('c.title')
            ->from('sys_category', 'c')
            ->join(
                'c',
                'sys_category_record_mm',
                'mm',
                $queryBuilder->expr()->eq('mm.uid_local', $queryBuilder->quoteIdentifier('c.uid'))
            )
            ->where(
                $queryBuilder->expr()->eq('mm.uid_foreign', $queryBuilder->createNamedParameter($pageUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('mm.tablenames', $queryBuilder->createNamedParameter('pages')),
                $queryBuilder->expr()->eq('mm.fieldname', $queryBuilder->createNamedParameter('categories'))
            )
            ->orderBy('mm.sorting')
            ->executeQuery();

        $categories = [];
        while ($row = $result->fetchAssociative()) {
            $categories[] = $row['title'];
        }

        return $categories;
    }

    protected function formatYamlFrontMatter(array $data): string
    {
        $yaml = "---\n";

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $yaml .= $key . ":\n";
                foreach ($value as $item) {
                    $yaml .= '  - ' . $this->escapeYamlValue($item) . "\n";
                }
            } else {
                $yaml .= $key . ': ' . $this->escapeYamlValue($value) . "\n";
            }
        }

        $yaml .= "---\n\n";

        return $yaml;
    }

    protected function escapeYamlValue(string $value): string
    {
        // If the value contains special characters, wrap in quotes
        if (preg_match('/[:#{}\[\]&*?|>!\'\"%@`]/', $value) || str_contains($value, "\n")) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }
        return $value;
    }
}
