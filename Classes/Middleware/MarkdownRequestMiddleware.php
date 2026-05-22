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

use B13\AiBotsLoveMarkdown\MarkdownPageType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Early middleware that detects markdown requests and cleans the request.
 *
 * This middleware runs BEFORE page-resolver to:
 * 1. Detect if markdown output is requested (via Accept header, query param, or URL suffix)
 * 2. Clean the request so TYPO3 renders the normal HTML page
 * 3. Set a marker attribute for the late response middleware
 */
final readonly class MarkdownRequestMiddleware implements MiddlewareInterface
{
    public const MARKER_ATTRIBUTE = 'ai-bots-love-markdown.requested';

    public function __construct(private readonly MarkdownPageType $markdownPageType)
    {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->isEnabled($request)) {
            return $handler->handle($request);
        }

        if (!$this->wantsMarkdown($request)) {
            return $handler->handle($request);
        }

        // Mark the request for markdown conversion
        $request = $request->withAttribute(self::MARKER_ATTRIBUTE, true);

        // Clean up the request: remove .md suffix and type parameter so normal page renders
        $request = $this->cleanRequest($request);

        return $handler->handle($request);
    }

    private function wantsMarkdown(ServerRequestInterface $request): bool
    {
        // Check for type=2026 (or whatever configured) query parameter
        $queryParams = $request->getQueryParams();
        $typeNum = $this->markdownPageType->getTypeNum($request->getAttribute('site'));
        if (isset($queryParams['type']) && (int)$queryParams['type'] === $typeNum) {
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
        $suffix = $this->markdownPageType->getSuffix($request->getAttribute('site'));
        if (str_ends_with($path, '/' . $suffix)) {
            return true;
        }

        return false;
    }

    private function isEnabled(ServerRequestInterface $request): bool
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return false;
        }

        return (bool)$site->getSettings()->get('ai_bots_love_markdown.enableContentNegotiation', true);
    }

    private function cleanRequest(ServerRequestInterface $request): ServerRequestInterface
    {
        $uri = $request->getUri();
        $path = $uri->getPath();
        $suffix = $this->markdownPageType->getSuffix($request->getAttribute('site'));
        // Remove /ai-bots-love.md suffix from path
        if (str_ends_with($path, '/' . $suffix)) {
            $path = substr($path, 0, -strlen('/' . $suffix)) ?: '/';
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
            if (str_ends_with($tail, '/' . $suffix)) {
                $tail = substr($tail, 0, -strlen('/' . $suffix));
            } elseif (str_ends_with($tail, $suffix)) {
                $tail = substr($tail, 0, -strlen($suffix));
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
}
