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

    public function __construct(
        private HtmlToMarkdownService $htmlToMarkdownService,
        private MetadataService $metadataService,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Only process HTML responses
        $contentType = $response->getHeaderLine('Content-Type');
        if (!str_contains($contentType, 'text/html')) {
            return $response;
        }

        if (!$request->getAttribute(MarkdownRequestMiddleware::CONTENT_NEGOTIATON_ENABLED_ATTRIBUTE)) {
            return $response;
        }

        $response = $response->withHeader('Vary', 'Accept');

        // Check if the early middleware marked this request for markdown conversion
        if (!$request->getAttribute(MarkdownRequestMiddleware::MARKDOWN_REQUESTED_ATTRIBUTE)) {
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

        // PSR-7 messages are immutable — every withX() call returns a new instance,
        // so the chain must be reassigned. Content-Length from the HTML response is
        // dropped because the body length changed; the frontend's content-length
        // middleware recalculates it. Without that, HTTP/2 clients close the stream.
        $response = $response
            ->withBody($body)
            ->withStatus(200)
            ->withHeader('Content-Type', 'text/markdown; charset=utf-8')
            ->withHeader('X-Robots-Tag', 'noindex')
            ->withoutHeader('Content-Length');

        return $response;
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
