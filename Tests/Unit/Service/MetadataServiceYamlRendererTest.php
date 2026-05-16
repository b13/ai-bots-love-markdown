<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown\Tests\Unit\Service;

use B13\AiBotsLoveMarkdown\Service\MetadataService;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class MetadataServiceYamlRendererTest extends UnitTestCase
{
    private function service(): MetadataService
    {
        return new MetadataService(
            $this->createMock(ConnectionPool::class),
            $this->createMock(EventDispatcherInterface::class),
        );
    }

    #[Test]
    public function rendersFlatScalarFrontMatter(): void
    {
        $yaml = $this->service()->renderYaml([
            'title' => 'Hello',
            'modified' => '2026-05-16',
        ]);

        self::assertSame(
            "---\ntitle: Hello\nmodified: 2026-05-16\n---\n\n",
            $yaml,
        );
    }

    #[Test]
    public function rendersListOfScalars(): void
    {
        $yaml = $this->service()->renderYaml([
            'keywords' => ['foo', 'bar'],
        ]);

        self::assertSame(
            "---\nkeywords:\n  - foo\n  - bar\n---\n\n",
            $yaml,
        );
    }

    #[Test]
    public function rendersListOfAssociativeArrays(): void
    {
        // upcoming_dates use case — each item is an associative array.
        $yaml = $this->service()->renderYaml([
            'upcoming_dates' => [
                ['start' => '2026-09-15', 'end' => '2026-09-16', 'location' => 'TAE Esslingen'],
                ['start' => '2027-03-12', 'end' => '2027-03-13', 'location' => 'Online'],
            ],
        ]);

        $expected = "---\nupcoming_dates:\n"
            . "  - start: 2026-09-15\n"
            . "    end: 2026-09-16\n"
            . "    location: TAE Esslingen\n"
            . "  - start: 2027-03-12\n"
            . "    end: 2027-03-13\n"
            . "    location: Online\n"
            . "---\n\n";
        self::assertSame($expected, $yaml);
    }

    #[Test]
    public function rendersNestedAssociativeMapping(): void
    {
        $yaml = $this->service()->renderYaml([
            'next_event' => ['start' => '2026-09-15', 'location' => 'Esslingen'],
        ]);

        $expected = "---\nnext_event:\n"
            . "  start: 2026-09-15\n"
            . "  location: Esslingen\n"
            . "---\n\n";
        self::assertSame($expected, $yaml);
    }

    #[Test]
    public function rendersIntegerCountWithoutThrowing(): void
    {
        $yaml = $this->service()->renderYaml([
            'course_count' => 47,
        ]);

        self::assertSame("---\ncourse_count: 47\n---\n\n", $yaml);
    }

    #[Test]
    public function rendersBooleanAndNullValues(): void
    {
        $yaml = $this->service()->renderYaml([
            'flag' => true,
            'optional' => null,
        ]);

        self::assertSame("---\nflag: true\noptional: null\n---\n\n", $yaml);
    }

    #[Test]
    public function rendersEmptyListAsBracketPair(): void
    {
        $yaml = $this->service()->renderYaml([
            'categories' => [],
        ]);

        self::assertSame("---\ncategories: []\n---\n\n", $yaml);
    }

    #[Test]
    public function quotesValuesWithSpecialCharacters(): void
    {
        $yaml = $this->service()->renderYaml([
            'title' => 'A: complicated [value]',
        ]);

        self::assertStringContainsString('title: "A: complicated [value]"', $yaml);
    }
}
