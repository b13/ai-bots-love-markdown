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

        $body = (string)$response->getBody();

        if (!str_contains($body, '<!-- markdown-')) {
            return $response;
        }

        $body = str_replace(self::MARKERS, '', $body);

        $stream = new Stream('php://temp', 'rw');
        $stream->write($body);
        $stream->rewind();

        return $response->withBody($stream);
    }
}
