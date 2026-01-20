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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

class MarkdownUrlRewriteMiddleware implements MiddlewareInterface
{
    private const TYPE_NUM = 2026;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $queryParams = $request->getQueryParams();

        // Check if request already has type=2000
        if (isset($queryParams['type']) && (int)$queryParams['type'] === self::TYPE_NUM) {
            return $handler->handle($request);
        }

        // Check if content negotiation is enabled for this site
        if (!$this->isContentNegotiationEnabled($request)) {
            return $handler->handle($request);
        }

        // Handle Accept header: text/markdown
        if ($this->acceptsMarkdown($request)) {
            $queryParams['type'] = self::TYPE_NUM;
            $uri = $request->getUri()->withQuery(http_build_query($queryParams));
            $request = $request->withUri($uri)->withQueryParams($queryParams);
        }

        return $handler->handle($request);
    }

    private function isContentNegotiationEnabled(ServerRequestInterface $request): bool
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return true;
        }

        return (bool)$site->getSettings()->get('ai_bots_love_markdown.enableContentNegotiation', true);
    }

    /**
     * Parse Accept header and check for text/markdown
     */
    private function acceptsMarkdown(ServerRequestInterface $request): bool
    {
        $acceptHeader = $request->getHeaderLine('Accept');
        $acceptTypes = array_map('trim', explode(',', $acceptHeader));

        foreach ($acceptTypes as $acceptType) {
            // Remove quality parameter if present
            $type = explode(';', $acceptType)[0];
            $type = trim($type);

            if ($type === 'text/markdown' || $type === 'text/x-markdown') {
                return true;
            }
        }

        return false;
    }
}
