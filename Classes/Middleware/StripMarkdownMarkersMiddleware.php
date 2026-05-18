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
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\Stream;

/**
 * Strips markdown extraction markers from frontend HTML responses.
 *
 * Markers are emitted by the bundled Fluid partials (MarkdownInclude /
 * MarkdownExclude) and end up cached together with the page body. They must
 * never be visible to human visitors, so this middleware removes them from
 * every text/html response.
 *
 * Markdown responses (Content-Type: text/markdown) already have markers
 * stripped during conversion in MarkdownResponseMiddleware and are skipped
 * by the content-type check below.
 */
final class StripMarkdownMarkersMiddleware implements MiddlewareInterface
{
    private const MARKERS = [
        '<!-- markdown-start -->',
        '<!-- markdown-end -->',
        '<!-- markdown-exclude-start -->',
        '<!-- markdown-exclude-end -->',
    ];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $contentType = $response->getHeaderLine('Content-Type');
        if (!str_contains($contentType, 'text/html')) {
            return $response;
        }

        // Debug bypass: when ?markdown-markers=1 is set, keep the markers in
        // the HTML response so integrators can see exactly which regions
        // their templates emit. Analogous to b13/vgwort-pro's vgwort-markers
        // query param. Gated to authenticated backend users OR non-production
        // contexts to prevent information disclosure to anonymous crawlers
        // on production sites.
        if (($request->getQueryParams()['markdown-markers'] ?? '') === '1'
            && $this->isDebugBypassAllowed($request)
        ) {
            return $response;
        }

        $body = (string)$response->getBody();

        if (!str_contains($body, '<!-- markdown-')) {
            return $response;
        }

        $body = str_replace(self::MARKERS, '', $body);

        $stream = new Stream('php://temp', 'rw');
        $stream->write($body);
        $stream->rewind();

        // Drop the original Content-Length header — the marker-stripped body
        // is shorter than the cached / upstream length. Leaving the header
        // in place produces an HTTP/2 INTERNAL_ERROR / RST_STREAM in browsers
        // because the declared length doesn't match the bytes on the wire,
        // and downstream document.readyState never reaches "complete".
        return $response->withBody($stream)->withoutHeader('Content-Length');
    }

    private function isDebugBypassAllowed(ServerRequestInterface $request): bool
    {
        $backendUser = $request->getAttribute('backend.user');
        if ($backendUser instanceof BackendUserAuthentication && !empty($backendUser->user)) {
            return true;
        }

        return !Environment::getContext()->isProduction();
    }
}
