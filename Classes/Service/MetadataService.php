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

use B13\AiBotsLoveMarkdown\Event\AfterFrontMatterForPageIsCreatedEvent;
use Doctrine\DBAL\ParameterType;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Frontend\Page\PageInformation;

final readonly class MetadataService
{
    public function __construct(
        protected ConnectionPool $connectionPool,
        protected EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Generate YAML front matter from HTML meta tags and page record.
     *
     * Meta tags take priority, page record is used as fallback.
     * Dispatches {@see AfterFrontMatterForPageIsCreatedEvent} so listeners can add or
     * modify entries before the array is rendered to YAML.
     */
    public function generateFrontMatter(
        string $html,
        array $pageRecord,
        string $fallbackUrl,
        ?PageInformation $pageInformation = null,
        ?ServerRequestInterface $request = null,
    ): string {
        $frontMatter = $this->buildFrontMatterData($html, $pageRecord, $fallbackUrl);

        $event = $this->eventDispatcher->dispatch(
            new AfterFrontMatterForPageIsCreatedEvent(
                frontMatter: $frontMatter,
                pageRecord: $pageRecord,
                html: $html,
                pageInformation: $pageInformation,
                request: $request,
            )
        );

        return $this->renderYaml($event->frontMatter);
    }

    /**
     * Assemble the front-matter data array from HTML meta tags and the page record.
     *
     * Exposed as a separate step so integrators with their own rendering pipeline
     * can grab the structured array directly.
     */
    public function buildFrontMatterData(string $html, array $pageRecord, string $fallbackUrl): array
    {
        // Extract metadata from HTML meta tags
        $metaTags = $this->extractMetaTags($html);

        $frontMatter = [];

        // Title: og:title > meta title > page record.
        // pageRecord values come straight from TYPO3 and may carry HTML entities
        // (e.g. "Automotive &amp; Mobility") — decode them so the YAML output
        // matches what a human reader sees in the rendered page title.
        $frontMatter['title'] = $metaTags['og:title']
            ?? $metaTags['title']
            ?? $this->decodeEntities((string)($pageRecord['title'] ?? ''));

        // URL: canonical > fallback URL
        $frontMatter['url'] = $metaTags['canonical'] ?? $fallbackUrl;

        // Description: og:description > meta description > page record
        $description = $metaTags['og:description']
            ?? $metaTags['description']
            ?? $this->decodeEntities((string)($pageRecord['description'] ?? ''));
        if ($description !== '') {
            $frontMatter['description'] = $description;
        }

        // Image: og:image
        if (!empty($metaTags['og:image'])) {
            $frontMatter['image'] = $metaTags['og:image'];
        }

        // Author: meta author > page record
        $author = $metaTags['author'] ?? $this->decodeEntities((string)($pageRecord['author'] ?? ''));
        if ($author !== '') {
            $frontMatter['author'] = $author;
        }

        // Dates from page record (no standard meta tags for these).
        // SYS_LASTCHANGED is intentionally not surfaced as a separate
        // "lastUpdated" key — it's almost always identical to tstamp and just
        // adds noise. Listeners can add their own date keys via
        // ModifyFrontMatterDataEvent when they need a domain-specific date.
        if (!empty($pageRecord['crdate'])) {
            $frontMatter['date'] = date('Y-m-d', (int)$pageRecord['crdate']);
        }
        if (!empty($pageRecord['tstamp'])) {
            $frontMatter['modified'] = date('Y-m-d', (int)$pageRecord['tstamp']);
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

        return $frontMatter;
    }

    /**
     * Render a front-matter data array to a YAML front-matter block.
     */
    public function renderYaml(array $frontMatter): string
    {
        return $this->formatYamlFrontMatter($frontMatter);
    }

    /**
     * Extract meta tags from HTML
     */
    /**
     * Decode named / numeric HTML entities so values carried over from a page
     * record (or any other not-yet-decoded source) render cleanly in YAML.
     *
     * We run the decode pass twice on purpose: some TYPO3 installations
     * double-encode `og:title` content attributes (the raw page title is
     * already entity-escaped and then the meta-tag renderer escapes it again),
     * which leaves us with `&amp;amp;` in the source HTML. A single decode
     * pass on a single-encoded `&amp;` collapses correctly to `&`; the second
     * pass is a no-op when there's nothing left to decode.
     */
    private function decodeEntities(string $value): string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    protected function extractMetaTags(string $html): array
    {
        $meta = [];

        // Extract <title> tag
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
            $meta['title'] = trim($this->decodeEntities($matches[1]));
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
                $meta[$name] = $this->decodeEntities($match[2]);
            }
        }

        // Also check reverse order (content before name)
        preg_match_all('/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']([^"\']+)["\'][^>]*>/i', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $name = strtolower($match[2]);
            if (in_array($name, ['description', 'keywords', 'author'], true) && !isset($meta[$name])) {
                $meta[$name] = $this->decodeEntities($match[1]);
            }
        }

        // Extract Open Graph meta tags
        preg_match_all('/<meta[^>]+property=["\']([^"\']+)["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $property = strtolower($match[1]);
            if (str_starts_with($property, 'og:')) {
                $meta[$property] = $this->decodeEntities($match[2]);
            }
        }

        // Also check reverse order (content before property)
        preg_match_all('/<meta[^>]+content=["\']([^"\']*)["\'][^>]+property=["\']([^"\']+)["\'][^>]*>/i', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $property = strtolower($match[2]);
            if (str_starts_with($property, 'og:') && !isset($meta[$property])) {
                $meta[$property] = $this->decodeEntities($match[1]);
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
            $yaml .= $this->renderYamlPair((string)$key, $value, 0);
        }
        $yaml .= "---\n\n";

        return $yaml;
    }

    /**
     * Render a single key/value pair as YAML. Supports nested lists (each
     * list item can itself be a list of scalars or an associative array)
     * so a listener that adds, e.g., `upcoming_dates: [{start, end, location}, ...]`
     * doesn't crash the renderer.
     *
     * @param mixed $value
     */
    private function renderYamlPair(string $key, mixed $value, int $depth): string
    {
        $indent = str_repeat('  ', $depth);
        if (!is_array($value)) {
            return $indent . $key . ': ' . $this->escapeYamlValue($value) . "\n";
        }
        if ($value === []) {
            return $indent . $key . ": []\n";
        }
        $yaml = $indent . $key . ":\n";
        if (array_is_list($value)) {
            foreach ($value as $item) {
                $yaml .= $this->renderYamlListItem($item, $depth + 1);
            }
            return $yaml;
        }
        // Associative array — render as nested mapping.
        foreach ($value as $childKey => $childValue) {
            $yaml .= $this->renderYamlPair((string)$childKey, $childValue, $depth + 1);
        }
        return $yaml;
    }

    /**
     * @param mixed $item
     */
    private function renderYamlListItem(mixed $item, int $depth): string
    {
        $indent = str_repeat('  ', $depth);
        if (!is_array($item)) {
            return $indent . '- ' . $this->escapeYamlValue($item) . "\n";
        }
        if ($item === []) {
            return $indent . "- []\n";
        }
        if (array_is_list($item)) {
            // List of lists — render the inner list under a dash-anchored block.
            $yaml = $indent . "-\n";
            foreach ($item as $sub) {
                $yaml .= $this->renderYamlListItem($sub, $depth + 1);
            }
            return $yaml;
        }
        // Associative array as a list item — first pair shares the dash line,
        // subsequent pairs are aligned to the same indent.
        $yaml = '';
        $first = true;
        foreach ($item as $childKey => $childValue) {
            if ($first) {
                $first = false;
                if (is_array($childValue)) {
                    // Complex first value: dash on its own line, child indented.
                    $yaml .= $indent . "-\n" . $this->renderYamlPair((string)$childKey, $childValue, $depth + 1);
                } else {
                    $yaml .= $indent . '- ' . (string)$childKey . ': ' . $this->escapeYamlValue($childValue) . "\n";
                }
                continue;
            }
            // Subsequent keys: indent matches the position after "- " (depth + 1).
            $yaml .= $this->renderYamlPair((string)$childKey, $childValue, $depth + 1);
        }
        return $yaml;
    }

    /**
     * Escape a scalar value for safe YAML emission. Accepts scalars (string,
     * int, float, bool) and null so callers don't have to cast every numeric
     * count or boolean flag they want to surface.
     */
    protected function escapeYamlValue(string|int|float|bool|null $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        $string = (string)$value;
        // Quote when the value contains YAML-reserved characters or newlines.
        if (preg_match('/[:#{}\[\]&*?|>!\'\"%@`]/', $string) || str_contains($string, "\n")) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $string) . '"';
        }
        return $string;
    }
}
