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

use B13\AiBotsLoveMarkdown\MarkdownPageType;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Cache\CacheDataCollector;
use TYPO3\CMS\Core\Cache\CacheTag;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\ErrorController;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * TypoScript USER entry point for the llms.txt page type.
 *
 * Only answers on the site root and only when `llmsTxt.enabled` is set,
 * otherwise 404. Cached in the page cache; every traversed page adds its
 * `pageId_<uid>` cache tag so editing the tree invalidates the file.
 */
final readonly class LlmsTxtController
{
    private const MAX_DEPTH = 99;

    public function __construct(
        private LlmsTxtPageSelector $pageSelector,
        private LlmsTxtRenderer $renderer,
        private MarkdownPageType $markdownPageType,
    ) {}

    public function render(string $content, array $conf, ServerRequestInterface $request): string
    {
        $site = $request->getAttribute('site');
        $pageInformation = $request->getAttribute('frontend.page.information');
        if (!$site instanceof Site || !$pageInformation instanceof PageInformation) {
            $this->notFound($request, 'llms.txt requires a site');
        }

        $settings = LlmsTxtSettings::fromSite($site);
        if (!$settings->enabled) {
            $this->notFound($request, 'llms.txt is not enabled for this site');
        }
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        $rootPageId = $site->getRootPageId();
        // Language-overlaid root record (also when the root is a shortcut and
        // the current page is its target).
        $rootPage = $pageRepository->getPage($rootPageId);
        if (!self::isSiteRootRequest($pageInformation->getId(), $rootPageId, $this->resolveShortcutTarget($pageRepository, $rootPage))) {
            $this->notFound($request, 'llms.txt is only available on the site root');
        }

        $languageAspect = GeneralUtility::makeInstance(Context::class)->getAspect('language');
        $traversedUids = [$rootPageId];
        $level1Pages = $this->loadChildren($pageRepository, $languageAspect, $rootPageId, 1, $traversedUids);
        $this->addCacheTags($request, $traversedUids);

        $title = $settings->title !== '' ? $settings->title : (string)($rootPage['title'] ?? '');
        $description = $settings->description !== '' ? $settings->description : (string)($rootPage['description'] ?? '');

        $language = $request->getAttribute('language');
        $markdownTypeNum = $this->markdownPageType->getTypeNum($site);
        $router = $site->getRouter();

        return $this->renderer->render(
            $title,
            $description,
            $this->pageSelector->select($level1Pages, $site, $settings),
            static function (int $uid) use ($router, $language, $markdownTypeNum): string {
                try {
                    return (string)$router->generateUri($uid, [
                        '_language' => $language,
                        'type' => $markdownTypeNum,
                    ]);
                } catch (\Throwable) {
                    // One broken page must not take down the whole file.
                    return '';
                }
            },
        );
    }

    /**
     * llms.txt is served on the site root only. When the root is a shortcut,
     * TYPO3 redirects /llms.txt to /<target>/llms.txt — accept that target.
     */
    public static function isSiteRootRequest(int $currentPageId, int $rootPageId, ?int $rootShortcutTargetId): bool
    {
        return $currentPageId === $rootPageId
            || ($rootShortcutTargetId !== null && $currentPageId === $rootShortcutTargetId);
    }

    private function resolveShortcutTarget(PageRepository $pageRepository, array $rootPage): ?int
    {
        if ((int)($rootPage['doktype'] ?? 0) !== PageRepository::DOKTYPE_SHORTCUT) {
            return null;
        }
        try {
            $target = $pageRepository->resolveShortcutPage($rootPage);
        } catch (\Throwable) {
            return null;
        }
        $targetUid = (int)($target['uid'] ?? 0);

        return $targetUid > 0 ? $targetUid : null;
    }

    /**
     * Loads visible subpages (hidden/time/group restrictions and language
     * overlay applied by PageRepository) recursively, in tree order.
     *
     * @param int[] $traversedUids
     * @return list<array<string, mixed>>
     */
    private function loadChildren(
        PageRepository $pageRepository,
        LanguageAspect $languageAspect,
        int $pid,
        int $depth,
        array &$traversedUids,
    ): array
    {
        if ($depth > self::MAX_DEPTH) {
            return [];
        }

        $pages = [];
        foreach ($pageRepository->getMenu($pid, '*', 'sorting', '', false) as $page) {
            $uid = (int)($page['uid'] ?? 0);
            if ($uid === 0 || in_array($uid, $traversedUids, true)) {
                continue;
            }
            $traversedUids[] = $uid;
            // getMenu() falls back to the default language record for pages
            // without a suitable translation: list them only if the page is
            // really available in the requested language, keep walking children.
            $page[LlmsTxtPageSelector::UNAVAILABLE_IN_LANGUAGE] = !$pageRepository->isPageSuitableForLanguage($page, $languageAspect);
            $page['children'] = $this->loadChildren($pageRepository, $languageAspect, $uid, $depth + 1, $traversedUids);
            $pages[] = $page;
        }

        return $pages;
    }

    /**
     * @param int[] $uids
     */
    private function addCacheTags(ServerRequestInterface $request, array $uids): void
    {
        $collector = $request->getAttribute('frontend.cache.collector');
        if (!$collector instanceof CacheDataCollector) {
            return;
        }
        $collector->addCacheTags(...array_map(
            static fn(int $uid): CacheTag => new CacheTag('pageId_' . $uid),
            $uids,
        ));
    }

    private function notFound(ServerRequestInterface $request, string $reason): never
    {
        throw new ImmediateResponseException(
            GeneralUtility::makeInstance(ErrorController::class)->pageNotFoundAction($request, $reason),
            1763300000,
        );
    }
}
