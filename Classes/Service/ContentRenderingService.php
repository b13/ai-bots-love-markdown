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

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class ContentRenderingService
{
    public function __construct(
        protected readonly BackendLayoutView $backendLayoutView,
    ) {}

    /**
     * Render content from specified colPos values or all from backend layout
     *
     * @param ServerRequestInterface $request
     * @param string $configuredColPos Comma-separated list of colPos values, empty = use backend layout
     * @param int $pageId
     * @return string Rendered HTML content
     */
    public function renderContent(ServerRequestInterface $request, string $configuredColPos, int $pageId): string
    {
        $colPosList = $this->getColPosList($configuredColPos, $pageId);

        if ($colPosList === []) {
            return '';
        }

        $content = '';
        foreach ($colPosList as $colPos) {
            $content .= $this->renderColPos($request, $colPos);
        }

        return $content;
    }

    /**
     * Get the list of colPos to render
     *
     * @param string $configuredColPos Comma-separated list or empty
     * @param int $pageId
     * @return int[]
     */
    protected function getColPosList(string $configuredColPos, int $pageId): array
    {
        // If explicitly configured, use that list
        if ($configuredColPos !== '') {
            $colPosList = GeneralUtility::intExplode(',', $configuredColPos, true);
            // Ensure colPos 0 is first if it's in the list
            return $this->sortColPosListWithZeroFirst($colPosList);
        }

        // Otherwise, get from backend layout
        return $this->getColPosFromBackendLayout($pageId);
    }

    /**
     * Get colPos list from the page's backend layout
     */
    protected function getColPosFromBackendLayout(int $pageId): array
    {
        try {
            $backendLayout = $this->backendLayoutView->getBackendLayoutForPage($pageId);
            $colPosList = array_map('intval', $backendLayout->getColumnPositionNumbers());
            return $this->sortColPosListWithZeroFirst($colPosList);
        } catch (\Exception) {
            // Fallback to colPos 0 if backend layout cannot be determined
            return [0];
        }
    }

    /**
     * Sort colPos list ensuring 0 is first (if present)
     *
     * @param int[] $colPosList
     * @return int[]
     */
    protected function sortColPosListWithZeroFirst(array $colPosList): array
    {
        if ($colPosList === []) {
            return [0];
        }

        // Remove duplicates and sort
        $colPosList = array_unique($colPosList);
        sort($colPosList);

        // If 0 is in the list, move it to the front
        $zeroKey = array_search(0, $colPosList, true);
        if ($zeroKey !== false) {
            unset($colPosList[$zeroKey]);
            array_unshift($colPosList, 0);
        }

        return array_values($colPosList);
    }

    /**
     * Render content from a specific colPos
     */
    protected function renderColPos(ServerRequestInterface $request, int $colPos): string
    {
        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $cObj->setRequest($request);

        $conf = [
            'table' => 'tt_content',
            'select.' => [
                'orderBy' => 'sorting',
                'where' => 'colPos = ' . $colPos,
            ],
        ];

        return $cObj->cObjGetSingle('CONTENT', $conf);
    }
}
