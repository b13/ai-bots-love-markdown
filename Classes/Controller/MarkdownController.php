<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown\Controller;

use B13\AiBotsLoveMarkdown\Service\ContentRenderingService;
use B13\AiBotsLoveMarkdown\Service\HtmlToMarkdownService;
use B13\AiBotsLoveMarkdown\Service\MetadataService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsAllowedCallable;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Page\PageInformation;
use TYPO3\CMS\Frontend\Typolink\LinkFactory;

final readonly class MarkdownController
{
    public function __construct(
        protected HtmlToMarkdownService $htmlToMarkdownService,
        protected MetadataService $metadataService,
        protected ContentRenderingService $contentRenderingService,
        protected LinkFactory $linkFactory,
    ) {}

    #[AsAllowedCallable]
    public function render(string $content, array $conf, ServerRequestInterface $request): string
    {
        $pageInformation = $this->getPageInformation($request);
        if ($pageInformation === null) {
            return '';
        }

        $pageRecord = $pageInformation->getPageRecord();
        $pageUid = (int)$pageRecord['uid'];
        $pageUrl = $this->getPageUrl($request, $pageUid);
        $baseUrl = $this->getBaseUrl($request);

        // Generate front matter
        $frontMatter = $this->metadataService->generateFrontMatter($pageRecord, $pageUrl);

        // Get the page title as H1
        $pageTitle = '# ' . ($pageRecord['title'] ?? '') . "\n\n";

        // Render content elements from configured colPos (empty = all from backend layout)
        $configuredColPos = trim($conf['colPos'] ?? '');
        $contentHtml = $this->contentRenderingService->renderContent(
            $request,
            $configuredColPos,
            $pageUid
        );

        // Convert HTML to Markdown
        $contentMarkdown = $this->htmlToMarkdownService->convert($contentHtml, $baseUrl);

        return $frontMatter . $pageTitle . $contentMarkdown;
    }

    protected function getPageUrl(ServerRequestInterface $request, int $pageUid): string
    {
        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $cObj->setRequest($request);

        try {
            $linkResult = $this->linkFactory->create('', ['parameter' => 't3://page?uid=' . $pageUid], $cObj);
            return $linkResult->getUrl();
        } catch (\Exception) {
            // Fallback to site base URL if link building fails
            return $this->getBaseUrl($request);
        }
    }

    protected function getBaseUrl(ServerRequestInterface $request): string
    {
        /** @var SiteInterface|null $site */
        $site = $request->getAttribute('site');
        if ($site !== null) {
            return rtrim((string)$site->getBase(), '/');
        }

        $uri = $request->getUri();
        return $uri->getScheme() . '://' . $uri->getHost();
    }

    protected function getPageInformation(ServerRequestInterface $request): ?PageInformation
    {
        return $request->getAttribute('frontend.page.information');
    }
}
