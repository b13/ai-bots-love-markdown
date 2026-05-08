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

use B13\AiBotsLoveMarkdown\Middleware\MarkdownResponseMiddleware;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Tests the marker-related private methods of MarkdownResponseMiddleware
 * (extractMainContent, removeExcludedSections, stripMarkers, extractBetweenMarkers)
 * via reflection. The middleware is exercised end-to-end by functional tests.
 */
final class MarkdownResponseMiddlewareMarkerTest extends UnitTestCase
{
    private function invoke(string $method, string $input): string|null
    {
        $reflection = new ReflectionClass(MarkdownResponseMiddleware::class);
        $middleware = $reflection->newInstanceWithoutConstructor();
        return $reflection->getMethod($method)->invoke($middleware, $input);
    }

    #[Test]
    public function extractMainContentPrefersMarkersOverMain(): void
    {
        $html = '<html><body><main><p>main</p></main>'
            . '<!-- markdown-start --><p>marked</p><!-- markdown-end --></body></html>';

        self::assertSame('<p>marked</p>', $this->invoke('extractMainContent', $html));
    }

    #[Test]
    public function extractMainContentFallsBackToMainWhenNoMarkers(): void
    {
        $html = '<html><body><main><p>main</p></main></body></html>';

        self::assertSame('<p>main</p>', $this->invoke('extractMainContent', $html));
    }

    #[Test]
    public function extractMainContentFallsBackToBodyWhenNoMainOrMarkers(): void
    {
        $html = '<html><body><p>body</p></body></html>';

        self::assertSame('<p>body</p>', $this->invoke('extractMainContent', $html));
    }

    #[Test]
    public function removeExcludedSectionsDropsSimpleRegion(): void
    {
        $html = '<p>keep</p>'
            . '<!-- markdown-exclude-start --><div>drop</div><!-- markdown-exclude-end -->'
            . '<p>also keep</p>';

        $out = $this->invoke('removeExcludedSections', $html);
        self::assertStringNotContainsString('drop', (string)$out);
        self::assertStringContainsString('keep', (string)$out);
        self::assertStringContainsString('also keep', (string)$out);
    }

    #[Test]
    public function removeExcludedSectionsHandlesNesting(): void
    {
        $html = '<p>before</p>'
            . '<!-- markdown-exclude-start -->outer'
            . '<!-- markdown-exclude-start -->inner<!-- markdown-exclude-end -->'
            . 'outer-tail<!-- markdown-exclude-end -->'
            . '<p>after</p>';

        $out = $this->invoke('removeExcludedSections', $html);
        self::assertStringNotContainsString('outer', (string)$out);
        self::assertStringNotContainsString('inner', (string)$out);
        self::assertStringContainsString('before', (string)$out);
        self::assertStringContainsString('after', (string)$out);
    }

    #[Test]
    public function removeExcludedSectionsLeavesUnpairedStartAlone(): void
    {
        $html = '<p>before</p><!-- markdown-exclude-start --><p>orphan</p>';

        self::assertSame($html, $this->invoke('removeExcludedSections', $html));
    }

    #[Test]
    public function stripMarkersRemovesAllFourMarkerVariants(): void
    {
        $html = '<!-- markdown-start --><!-- markdown-end -->'
            . '<!-- markdown-exclude-start --><!-- markdown-exclude-end -->X';

        self::assertSame('X', $this->invoke('stripMarkers', $html));
    }
}
