<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown\Middleware;

use B13\AiBotsLoveMarkdown\Event\AfterMarkdownConversionEvent;
use B13\AiBotsLoveMarkdown\Service\HtmlToMarkdownService;
use B13\AiBotsLoveMarkdown\Service\MetadataService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\Page\PageInformation;

final readonly class MarkdownResponseMiddleware implements MiddlewareInterface
{
    private const MARKDOWN_LINK_PATTERN = '/<link[^>]+rel=["\']alternate["\'][^>]+type=["\']text\/markdown["\'][^>]*>/i';
    private const MARKDOWN_URL_SUFFIX = '/ai-bots-love.md';

    public function __construct(
        private HtmlToMarkdownService $htmlToMarkdownService,
        private MetadataService $metadataService,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Check if this request wants markdown output
        if (!$this->wantsMarkdown($request)) {
            return $handler->handle($request);
        }

        // Check if content negotiation is enabled for this site
        if (!$this->isEnabled($request)) {
            return $handler->handle($request);
        }

        // Clean up the request: remove .md suffix and type parameter so normal page renders
        $request = $this->cleanRequest($request);

        // Let the normal page render
        $response = $handler->handle($request);

        // Only process HTML responses
        $contentType = $response->getHeaderLine('Content-Type');
        if (!str_contains($contentType, 'text/html')) {
            return $response;
        }

        // Get the HTML content
        $html = (string)$response->getBody();

        // Check if the page has the markdown alternate link (opt-in check)
        if (!preg_match(self::MARKDOWN_LINK_PATTERN, $html)) {
            return $response;
        }

        // Convert to markdown
        $markdown = $this->convertToMarkdown($request, $html);

        // Return markdown response
        $body = new Stream('php://temp', 'rw');
        $body->write($markdown);
        $body->rewind();

        return new Response(
            $body,
            200,
            [
                'Content-Type' => 'text/markdown; charset=utf-8',
                'X-Robots-Tag' => 'noindex',
            ]
        );
    }

    private function wantsMarkdown(ServerRequestInterface $request): bool
    {
        // Check for type=2026 query parameter
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['type']) && (int)$queryParams['type'] === 2026) {
            return true;
        }

        // Check Accept header
        $acceptHeader = $request->getHeaderLine('Accept');
        if ($acceptHeader !== '') {
            $acceptTypes = array_map('trim', explode(',', $acceptHeader));
            foreach ($acceptTypes as $acceptType) {
                $type = trim(explode(';', $acceptType)[0]);
                if ($type === 'text/markdown' || $type === 'text/x-markdown') {
                    return true;
                }
            }
        }

        // Check for /ai-bots-love.md suffix in path
        $path = $request->getUri()->getPath();
        if (str_ends_with($path, self::MARKDOWN_URL_SUFFIX)) {
            return true;
        }

        return false;
    }

    private function isEnabled(ServerRequestInterface $request): bool
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return true;
        }

        return (bool)$site->getSettings()->get('ai_bots_love_markdown.enableContentNegotiation', true);
    }

    private function cleanRequest(ServerRequestInterface $request): ServerRequestInterface
    {
        $uri = $request->getUri();
        $path = $uri->getPath();

        // Remove /ai-bots-love.md suffix from path
        if (str_ends_with($path, self::MARKDOWN_URL_SUFFIX)) {
            $path = substr($path, 0, -strlen(self::MARKDOWN_URL_SUFFIX));
            $uri = $uri->withPath($path);
            $request = $request->withUri($uri);
        }

        // Remove type parameter from query string
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['type'])) {
            unset($queryParams['type']);
            $request = $request->withQueryParams($queryParams);
            $uri = $uri->withQuery(http_build_query($queryParams));
            $request = $request->withUri($uri);
        }

        // Update the routing attribute with cleaned URI and tail
        $routeResult = $request->getAttribute('routing');
        if ($routeResult instanceof SiteRouteResult) {
            $tail = $routeResult->getTail();
            // Remove /ai-bots-love.md suffix from tail
            if (str_ends_with($tail, self::MARKDOWN_URL_SUFFIX)) {
                $tail = substr($tail, 0, -strlen(self::MARKDOWN_URL_SUFFIX));
            }
            $routeResult = new SiteRouteResult(
                $uri,
                $routeResult->getSite(),
                $routeResult->getLanguage(),
                $tail
            );
            $request = $request->withAttribute('routing', $routeResult);
        }

        return $request;
    }

    private function convertToMarkdown(ServerRequestInterface $request, string $html): string
    {
        // Extract page information for fallback data
        $pageInformation = $request->getAttribute('frontend.page.information');
        $pageRecord = [];
        $fallbackUrl = (string)$request->getUri();

        if ($pageInformation instanceof PageInformation) {
            $pageRecord = $pageInformation->getPageRecord();
        }

        // Get base URL for making relative URLs absolute
        $site = $request->getAttribute('site');
        $baseUrl = $site instanceof Site ? rtrim((string)$site->getBase(), '/') : '';

        // Extract main content (prefer <main>, fallback to <body>)
        $content = $this->extractMainContent($html);

        // Generate front matter from meta tags (with page record as fallback)
        $frontMatter = $this->metadataService->generateFrontMatter($html, $pageRecord, $fallbackUrl);

        // Get page title for H1 (from og:title, title tag, or page record)
        $pageTitle = $this->extractPageTitle($html, $pageRecord);

        // Convert HTML to Markdown
        $markdownContent = $pageTitle . $this->htmlToMarkdownService->convert($content, $baseUrl);

        // Dispatch event to allow modification of the markdown output
        $event = $this->eventDispatcher->dispatch(
            new AfterMarkdownConversionEvent(
                $html,
                $frontMatter,
                $markdownContent,
                $pageInformation,
                $request,
            )
        );

        return $event->frontMatter . $event->markdownContent;
    }

    private function extractPageTitle(string $html, array $pageRecord): string
    {
        // Try og:title first
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            return '# ' . html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') . "\n\n";
        }
        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:title["\'][^>]*>/i', $html, $matches)) {
            return '# ' . html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') . "\n\n";
        }

        // Try <title> tag
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
            return '# ' . trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')) . "\n\n";
        }

        // Fallback to page record
        if (!empty($pageRecord['title'])) {
            return '# ' . $pageRecord['title'] . "\n\n";
        }

        return '';
    }

    private function extractMainContent(string $html): string
    {
        // Try to extract <main> content first
        if (preg_match('/<main[^>]*>(.*?)<\/main>/is', $html, $matches)) {
            return $matches[1];
        }

        // Fallback to <body> content
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
            return $matches[1];
        }

        // Last resort: return the whole HTML
        return $html;
    }
}
