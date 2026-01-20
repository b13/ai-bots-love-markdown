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

    public function generateFrontMatter(array $pageRecord, string $pageUrl): string
    {
        $frontMatter = [
            'title' => $pageRecord['title'] ?? '',
            'url' => $pageUrl,
        ];

        // Add creation date
        if (!empty($pageRecord['crdate'])) {
            $frontMatter['date'] = date('Y-m-d', (int)$pageRecord['crdate']);
        }

        // Add modification date
        if (!empty($pageRecord['tstamp'])) {
            $frontMatter['modified'] = date('Y-m-d', (int)$pageRecord['tstamp']);
        }

        // Add description if available
        if (!empty($pageRecord['description'])) {
            $frontMatter['description'] = $pageRecord['description'];
        }

        // Add categories
        $categories = $this->getPageCategories((int)($pageRecord['uid'] ?? 0));
        if (!empty($categories)) {
            $frontMatter['categories'] = $categories;
        }

        // Add author for blog posts
        if (!empty($pageRecord['author'])) {
            $frontMatter['author'] = $pageRecord['author'];
        }

        return $this->formatYamlFrontMatter($frontMatter);
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
