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
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class StripMarkdownMarkersMiddlewareTest extends UnitTestCase
{
    private ?ApplicationContext $originalContext = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Tests need a defined ApplicationContext for the debug-bypass gating.
        // Unit tests don't initialize Environment on their own — bootstrap a
        // Testing context here and restore whatever was there before in tearDown.
        try {
            $this->originalContext = Environment::getContext();
        } catch (\TypeError) {
            $this->originalContext = null;
        }
        $this->initializeEnvironmentWith(new ApplicationContext('Testing'));
    }

    protected function tearDown(): void
    {
        if ($this->originalContext instanceof ApplicationContext) {
            $this->initializeEnvironmentWith($this->originalContext);
        }
        parent::tearDown();
    }

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
    public function dropsContentLengthHeaderWhenBodyChanges(): void
    {
        // A stale Content-Length from cache / upstream that no longer matches
        // the shortened body must be removed — otherwise HTTP/2 closes the
        // stream with INTERNAL_ERROR and the browser's document.readyState
        // never reaches "complete".
        $html = '<html><body>'
            . '<!-- markdown-start --><p>Content</p><!-- markdown-end -->'
            . '</body></html>';

        $upstream = $this->createResponseWithBody($html)
            ->withHeader('Content-Length', (string)strlen($html));

        $middleware = new StripMarkdownMarkersMiddleware();
        $response = $middleware->process(
            new ServerRequest('https://example.com/', 'GET'),
            $this->createHandler($upstream),
        );

        self::assertFalse($response->hasHeader('Content-Length'));
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

    #[Test]
    public function debugQueryParamKeepsMarkersForBackendUser(): void
    {
        $html = '<html><body><!-- markdown-start --><p>x</p><!-- markdown-end --></body></html>';
        $beUser = $this->createMock(BackendUserAuthentication::class);
        $beUser->user = ['uid' => 1];

        $request = (new ServerRequest('https://example.com/?markdown-markers=1', 'GET'))
            ->withQueryParams(['markdown-markers' => '1'])
            ->withAttribute('backend.user', $beUser);

        $middleware = new StripMarkdownMarkersMiddleware();
        $response = $middleware->process(
            $request,
            $this->createHandler($this->createResponseWithBody($html))
        );

        self::assertStringContainsString('<!-- markdown-start -->', (string)$response->getBody());
    }

    #[Test]
    public function debugQueryParamIsIgnoredForAnonymousProductionRequest(): void
    {
        $this->initializeEnvironmentWith(new ApplicationContext('Production'));

        $html = '<html><body><!-- markdown-start --><p>x</p><!-- markdown-end --></body></html>';

        $request = (new ServerRequest('https://example.com/?markdown-markers=1', 'GET'))
            ->withQueryParams(['markdown-markers' => '1']);

        $middleware = new StripMarkdownMarkersMiddleware();
        $response = $middleware->process(
            $request,
            $this->createHandler($this->createResponseWithBody($html))
        );

        // Production + anonymous => markers must be stripped even with the
        // debug flag, otherwise crawlers could surface them.
        self::assertStringNotContainsString('<!-- markdown-', (string)$response->getBody());
    }

    #[Test]
    public function debugQueryParamKeepsMarkersInNonProductionAnonymousRequest(): void
    {
        // setUp() already put us in a Testing context.
        self::assertFalse(Environment::getContext()->isProduction());

        $html = '<html><body><!-- markdown-start --><p>x</p><!-- markdown-end --></body></html>';

        $request = (new ServerRequest('https://example.com/?markdown-markers=1', 'GET'))
            ->withQueryParams(['markdown-markers' => '1']);

        $middleware = new StripMarkdownMarkersMiddleware();
        $response = $middleware->process(
            $request,
            $this->createHandler($this->createResponseWithBody($html))
        );

        self::assertStringContainsString('<!-- markdown-start -->', (string)$response->getBody());
    }

    private function initializeEnvironmentWith(ApplicationContext $context): void
    {
        $projectPath = dirname(__DIR__, 3);
        Environment::initialize(
            $context,
            true,
            true,
            $projectPath,
            $projectPath,
            $projectPath . '/var',
            $projectPath . '/Configuration',
            $projectPath . '/vendor/bin/phpunit',
            'UNIX',
        );
    }
}
