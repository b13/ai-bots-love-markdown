<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown\Tests\Unit\Middleware;

use B13\AiBotsLoveMarkdown\Middleware\StripMarkdownMarkersMiddleware;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class StripMarkdownMarkersMiddlewareTest extends UnitTestCase
{
    private function createResponseWithBody(string $body, string $contentType = 'text/html; charset=utf-8'): ResponseInterface
    {
        $stream = new Stream('php://temp', 'rw');
        $stream->write($body);
        $stream->rewind();

        return (new Response())
            ->withHeader('Content-Type', $contentType)
            ->withBody($stream);
    }

    private function createHandler(ResponseInterface $response): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }

    #[Test]
    public function stripsAllMarkersFromHtmlResponse(): void
    {
        $html = '<html><body>'
            . '<!-- markdown-start --><p>Content</p><!-- markdown-end -->'
            . '<!-- markdown-exclude-start --><aside>Ad</aside><!-- markdown-exclude-end -->'
            . '</body></html>';

        $middleware = new StripMarkdownMarkersMiddleware();
        $response = $middleware->process(
            new ServerRequest('https://example.com/', 'GET'),
            $this->createHandler($this->createResponseWithBody($html))
        );

        $body = (string)$response->getBody();
        self::assertStringNotContainsString('<!-- markdown-', $body);
        self::assertStringContainsString('<p>Content</p>', $body);
        self::assertStringContainsString('<aside>Ad</aside>', $body);
    }

    #[Test]
    public function doesNotModifyNonHtmlResponse(): void
    {
        $json = '{"data": "<!-- markdown-start -->"}';

        $middleware = new StripMarkdownMarkersMiddleware();
        $response = $middleware->process(
            new ServerRequest('https://example.com/', 'GET'),
            $this->createHandler($this->createResponseWithBody($json, 'application/json'))
        );

        self::assertStringContainsString('<!-- markdown-start -->', (string)$response->getBody());
    }

    #[Test]
    public function doesNotModifyMarkdownResponse(): void
    {
        $markdown = "# Title\n\n<!-- markdown-start --> stays in code block";

        $middleware = new StripMarkdownMarkersMiddleware();
        $response = $middleware->process(
            new ServerRequest('https://example.com/', 'GET'),
            $this->createHandler($this->createResponseWithBody($markdown, 'text/markdown; charset=utf-8'))
        );

        self::assertStringContainsString('<!-- markdown-start -->', (string)$response->getBody());
    }

    #[Test]
    public function returnsOriginalResponseWhenNoMarkersPresent(): void
    {
        $html = '<html><body><p>No markers here</p></body></html>';

        $original = $this->createResponseWithBody($html);
        $middleware = new StripMarkdownMarkersMiddleware();
        $response = $middleware->process(
            new ServerRequest('https://example.com/', 'GET'),
            $this->createHandler($original)
        );

        self::assertSame($original, $response);
    }
}
