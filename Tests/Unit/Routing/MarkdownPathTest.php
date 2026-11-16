<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown\Tests\Unit\Routing;

use B13\AiBotsLoveMarkdown\Routing\MarkdownPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class MarkdownPathTest extends UnitTestCase
{
    public static function legacyProvider(): array
    {
        return [
            'page' => ['/a/ai-bots-love.md', '/a'],
            'root' => ['/ai-bots-love.md', '/'],
            'tail' => ['a/ai-bots-love.md', 'a'],
            'root tail' => ['ai-bots-love.md', ''],
        ];
    }

    #[Test]
    #[DataProvider('legacyProvider')]
    public function stripsLegacySuffixRegardlessOfDotMd(string $path, string $expected): void
    {
        self::assertSame($expected, MarkdownPath::strip($path, 'ai-bots-love.md', false));
        self::assertSame($expected, MarkdownPath::strip($path, 'ai-bots-love.md', true));
    }

    public static function dotMdProvider(): array
    {
        return [
            'page' => ['/a/b.md', '/a/b'],
            'root index' => ['/index.md', '/'],
            'language root index' => ['/en/index.md', '/en'],
            'legacy language root' => ['/en/ai-bots-love.md', '/en'],
            'bare root' => ['/.md', '/'],
            'root index tail' => ['index.md', ''],
            'page tail' => ['a/b.md', 'a/b'],
        ];
    }

    #[Test]
    #[DataProvider('dotMdProvider')]
    public function stripsDotMdWhenEnabled(string $path, string $expected): void
    {
        self::assertSame($expected, MarkdownPath::strip($path, 'ai-bots-love.md', true));
    }

    #[Test]
    public function ignoresDotMdWhenDisabled(): void
    {
        self::assertNull(MarkdownPath::strip('/a/b.md', 'ai-bots-love.md', false));
    }

    public static function nonMarkdownProvider(): array
    {
        return [
            'llms.txt' => ['/llms.txt'],
            'md inside slug' => ['/docs.md-guide'],
            'trailing slash' => ['/a/'],
            'plain page' => ['/a'],
        ];
    }

    #[Test]
    #[DataProvider('nonMarkdownProvider')]
    public function leavesNonMarkdownPathsAlone(string $path): void
    {
        self::assertNull(MarkdownPath::strip($path, 'ai-bots-love.md', true));
    }
}
