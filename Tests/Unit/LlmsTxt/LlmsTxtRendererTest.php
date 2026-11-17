<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown\Tests\Unit\LlmsTxt;

use B13\AiBotsLoveMarkdown\LlmsTxt\LlmsTxtEntry;
use B13\AiBotsLoveMarkdown\LlmsTxt\LlmsTxtRenderer;
use B13\AiBotsLoveMarkdown\LlmsTxt\LlmsTxtSection;
use B13\AiBotsLoveMarkdown\LlmsTxt\LlmsTxtStructure;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class LlmsTxtRendererTest extends UnitTestCase
{
    private function render(string $title, string $description, LlmsTxtStructure $structure): string
    {
        return (new LlmsTxtRenderer())->render(
            $title,
            $description,
            $structure,
            static fn(int $uid): string => 'https://x/' . $uid . '.md',
        );
    }

    #[Test]
    public function rendersFullDocument(): void
    {
        $structure = new LlmsTxtStructure(
            [new LlmsTxtSection('A', [new LlmsTxtEntry(1, 'One', 'n1'), new LlmsTxtEntry(2, 'Two', '')])],
            [new LlmsTxtEntry(3, 'Three', 'n3')],
        );

        self::assertSame(
            "# Site\n\n> About\n\n## A\n\n- [One](https://x/1.md): n1\n- [Two](https://x/2.md)\n\n## Optional\n\n- [Three](https://x/3.md): n3\n",
            $this->render('Site', 'About', $structure),
        );
    }

    #[Test]
    public function omitsEmptyDescriptionAndOptional(): void
    {
        $structure = new LlmsTxtStructure(
            [new LlmsTxtSection('A', [new LlmsTxtEntry(1, 'One', '')])],
            [],
        );

        self::assertSame(
            "# Site\n\n## A\n\n- [One](https://x/1.md)\n",
            $this->render('Site', '', $structure),
        );
    }

    #[Test]
    public function skipsEntriesWithoutUrlAndEmptySections(): void
    {
        $structure = new LlmsTxtStructure(
            [
                new LlmsTxtSection('A', [new LlmsTxtEntry(1, 'One', ''), new LlmsTxtEntry(2, 'Broken', '')]),
                new LlmsTxtSection('B', [new LlmsTxtEntry(2, 'Broken', '')]),
            ],
            [new LlmsTxtEntry(2, 'Broken', '')],
        );

        $markdown = (new LlmsTxtRenderer())->render(
            'Site',
            '',
            $structure,
            static fn(int $uid): string => $uid === 2 ? '' : 'https://x/' . $uid . '.md',
        );

        self::assertSame("# Site\n\n## A\n\n- [One](https://x/1.md)\n", $markdown);
    }

    #[Test]
    public function escapesBracketsAndCollapsesWhitespace(): void
    {
        $structure = new LlmsTxtStructure(
            [new LlmsTxtSection("Sec\ntion", [new LlmsTxtEntry(1, 'A [b]', "x\n  y ")])],
            [],
        );

        self::assertSame(
            "# Site\n\n> Line one line two\n\n## Sec tion\n\n- [A \\[b\\]](https://x/1.md): x y\n",
            $this->render('Site', "Line one\r\n line two", $structure),
        );
    }
}
