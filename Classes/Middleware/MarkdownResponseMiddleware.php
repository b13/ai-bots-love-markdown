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
use B13\AiBotsLoveMarkdown\Event\BuildHtmlMarkdownConverterEvent;
use B13\AiBotsLoveMarkdown\Service\HtmlToMarkdownService;
use B13\AiBotsLoveMarkdown\Service\MarkdownExclusionService;
use B13\AiBotsLoveMarkdown\Service\MetadataService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * Late middleware that converts HTML responses to Markdown.
 *
 * This middleware runs AFTER page rendering to:
 * 1. Check if markdown was requested (via marker attribute from early middleware)
 * 2. Access PageInformation which is now available
 * 3. Convert the HTML response to Markdown
 */
final readonly class MarkdownResponseMiddleware implements MiddlewareInterface
{
    private const MARKDOWN_LINK_PATTERN = '/<link[^>]+rel=["\']alternate["\'][^>]+type=["\']text\/markdown["\'][^>]*>/i';

    private const MARKER_START = '<!-- markdown-start -->';
    private const MARKER_END = '<!-- markdown-end -->';
    private const EXCLUDE_START = '<!-- markdown-exclude-start -->';
    private const EXCLUDE_END = '<!-- markdown-exclude-end -->';

    public function __construct(
        private HtmlToMarkdownService $htmlToMarkdownService,
        private MetadataService $metadataService,
        private EventDispatcherInterface $eventDispatcher,
        private MarkdownExclusionService $exclusionService,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $isMarkdownRequest = (bool)$request->getAttribute(MarkdownRequestMiddleware::MARKER_ATTRIBUTE);

        // Let the normal page render
        $response = $handler->handle($request);

        // We only ever touch HTML responses.
        if (!str_contains($response->getHeaderLine('Content-Type'), 'text/html')) {
            return $response;
        }

        // Per-page / per-doktype exclusion check runs for EVERY HTML response — not
        // just markdown requests — so that bots crawling the regular HTML don't see
        // a discovery link for a page that will never serve markdown.
        $pageRecord = [];
        $pageInformation = $request->getAttribute('frontend.page.information');
        if ($pageInformation instanceof PageInformation) {
            $pageRecord = $pageInformation->getPageRecord();
        }
        $site = $request->getAttribute('site');
        if ($this->exclusionService->isExcluded($pageRecord, $site instanceof Site ? $site : null)) {
            // For markdown requesters this is a graceful content-negotiation downgrade
            // to HTML; for regular visitors the discovery link is removed so the dead
            // alternate URL is never advertised.
            return $this->stripDiscoveryLink($response);
        }

        // Non-excluded pages: pass through unchanged unless a markdown alternate was requested.
        if (!$isMarkdownRequest) {
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

    private function convertToMarkdown(ServerRequestInterface $request, string $html): string
    {
        // Extract page information - now available because we run after page rendering
        $pageInformation = $request->getAttribute('frontend.page.information');
        $pageRecord = [];
        $fallbackUrl = (string)$request->getUri();

        if ($pageInformation instanceof PageInformation) {
            $pageRecord = $pageInformation->getPageRecord();
        }

        // Get base URL for making relative URLs absolute
        $site = $request->getAttribute('site');
        $baseUrl = $site instanceof Site ? rtrim((string)$site->getBase(), '/') : '';

        // Extract main content (prefer markers, then <main>, fallback to <body>)
        $content = $this->extractMainContent($html);

        // Drop any regions wrapped in <!-- markdown-exclude-start/end --> markers
        $content = $this->removeExcludedSections($content);

        // Defense-in-depth: strip residual markers before converter sees the HTML
        $content = $this->stripMarkers($content);

        // Generate front matter from meta tags (with page record as fallback).
        // Pass PageInformation + request so listeners on ModifyFrontMatterDataEvent
        // have full request context.
        $frontMatter = $this->metadataService->generateFrontMatter(
            $html,
            $pageRecord,
            $fallbackUrl,
            $pageInformation instanceof PageInformation ? $pageInformation : null,
            $request,
        );

        // Get page title for H1 (from og:title, title tag, or page record)
        $pageTitle = $this->extractPageTitle($html, $pageRecord);

        $buildHtmlConverterEvent = new BuildHtmlMarkdownConverterEvent($request);
        if ($site instanceof Site) {
            $removeElements = (string)$site->getSettings()->get('ai_bots_love_markdown.removeElements', '');
            if ($removeElements !== '') {
                $buildHtmlConverterEvent->mergeOptions(['remove_nodes' => $removeElements]);
            }
        }
        $this->eventDispatcher->dispatch($buildHtmlConverterEvent);
        $converter = $buildHtmlConverterEvent->getHtmlConverter();

        // Convert HTML to Markdown
        $markdownContent = $pageTitle . $this->htmlToMarkdownService->convert($content, $baseUrl, $converter);

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
        // Priority 1: explicit content region between markdown-start/end markers
        $markerContent = $this->extractBetweenMarkers($html);
        if ($markerContent !== null) {
            return $markerContent;
        }

        // Priority 2: <main> content
        if (preg_match('/<main[^>]*>(.*?)<\/main>/is', $html, $matches)) {
            return $matches[1];
        }

        // Priority 3: <body> content
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
            return $matches[1];
        }

        // Last resort: return the whole HTML
        return $html;
    }

    private function extractBetweenMarkers(string $html): ?string
    {
        $startPos = strpos($html, self::MARKER_START);
        $endPos = strpos($html, self::MARKER_END);

        if ($startPos === false || $endPos === false || $endPos <= $startPos) {
            return null;
        }

        $contentStart = $startPos + strlen(self::MARKER_START);
        return substr($html, $contentStart, $endPos - $contentStart);
    }

    /**
     * Remove regions wrapped in <!-- markdown-exclude-start --> ... <!-- markdown-exclude-end -->.
     * Depth-aware: nested exclude regions are handled correctly. Defensive against
     * incomplete markers (missing end / end-before-start: nothing is removed).
     */
    private function removeExcludedSections(string $html): string
    {
        $result = $html;
        $iterations = 0;
        $maxIterations = 100;

        while ($iterations < $maxIterations) {
            $startPos = strpos($result, self::EXCLUDE_START);
            if ($startPos === false) {
                break;
            }

            $depth = 1;
            $searchOffset = $startPos + strlen(self::EXCLUDE_START);
            $endPos = false;

            while ($depth > 0) {
                $nextStart = strpos($result, self::EXCLUDE_START, $searchOffset);
                $nextEnd = strpos($result, self::EXCLUDE_END, $searchOffset);

                if ($nextEnd === false) {
                    break 2;
                }

                if ($nextStart !== false && $nextStart < $nextEnd) {
                    $depth++;
                    $searchOffset = $nextStart + strlen(self::EXCLUDE_START);
                } else {
                    $depth--;
                    if ($depth === 0) {
                        $endPos = $nextEnd;
                    }
                    $searchOffset = $nextEnd + strlen(self::EXCLUDE_END);
                }
            }

            if ($endPos === false) {
                break;
            }

            $before = substr($result, 0, $startPos);
            $after = substr($result, $endPos + strlen(self::EXCLUDE_END));
            $result = $before . $after;

            $iterations++;
        }

        return $result;
    }

    private function stripMarkers(string $html): string
    {
        return str_replace(
            [self::MARKER_START, self::MARKER_END, self::EXCLUDE_START, self::EXCLUDE_END],
            '',
            $html
        );
    }

    /**
     * Remove the <link rel="alternate" type="text/markdown"> tag from the HTML
     * response so user agents don't follow a dead markdown URL for excluded pages.
     */
    private function stripDiscoveryLink(ResponseInterface $response): ResponseInterface
    {
        $html = (string)$response->getBody();
        $stripped = preg_replace(self::MARKDOWN_LINK_PATTERN, '', $html);
        if ($stripped === null || $stripped === $html) {
            return $response;
        }

        $body = new Stream('php://temp', 'rw');
        $body->write($stripped);
        $body->rewind();

        return $response->withBody($body)->withoutHeader('Content-Length');
    }
}
