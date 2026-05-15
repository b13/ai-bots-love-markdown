<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown\Tests\Unit\Backend\TcaCondition;

use B13\AiBotsLoveMarkdown\Backend\TcaCondition\HideOnExcludedDoktype;
use B13\AiBotsLoveMarkdown\Service\MarkdownExclusionService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class HideOnExcludedDoktypeTest extends UnitTestCase
{
    private function siteWithExcludedDoktypes(string $value): Site
    {
        $settings = SiteSettings::createFromSettingsTree([
            'ai_bots_love_markdown' => ['excludedDoktypes' => $value],
        ]);

        $site = $this->createMock(Site::class);
        $site->method('getSettings')->willReturn($settings);

        return $site;
    }

    private function siteFinderResolving(int $pageId, Site $site): SiteFinder
    {
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->with($pageId)->willReturn($site);

        return $siteFinder;
    }

    #[Test]
    public function showsFieldForContentDoktype(): void
    {
        $condition = new HideOnExcludedDoktype(
            new MarkdownExclusionService(),
            $this->siteFinderResolving(123, $this->siteWithExcludedDoktypes('3,4,254')),
        );

        self::assertTrue($condition->match([
            'record' => ['uid' => 123, 'pid' => 1, 'doktype' => 1],
        ]));
    }

    #[Test]
    public function hidesFieldForDoktypeInExcludedList(): void
    {
        $condition = new HideOnExcludedDoktype(
            new MarkdownExclusionService(),
            $this->siteFinderResolving(123, $this->siteWithExcludedDoktypes('3,4,254')),
        );

        self::assertFalse($condition->match([
            'record' => ['uid' => 123, 'pid' => 1, 'doktype' => 254],
        ]));
    }

    #[Test]
    public function fallsBackToPidForNewRecords(): void
    {
        $condition = new HideOnExcludedDoktype(
            new MarkdownExclusionService(),
            $this->siteFinderResolving(1, $this->siteWithExcludedDoktypes('254')),
        );

        // New record: no uid yet, pid points to parent
        self::assertTrue($condition->match([
            'record' => ['pid' => 1, 'doktype' => 1],
        ]));
    }

    #[Test]
    public function showsFieldWhenSiteCannotBeResolved(): void
    {
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willThrowException(
            new SiteNotFoundException('no site', 0),
        );

        $condition = new HideOnExcludedDoktype(new MarkdownExclusionService(), $siteFinder);

        // When we can't resolve a site, fall open — better to show the field than hide it.
        self::assertTrue($condition->match([
            'record' => ['uid' => 999, 'pid' => 1, 'doktype' => 1],
        ]));
    }

    #[Test]
    public function showsFieldWhenRecordHasNoPidOrUid(): void
    {
        $condition = new HideOnExcludedDoktype(
            new MarkdownExclusionService(),
            $this->createMock(SiteFinder::class),
        );

        self::assertTrue($condition->match([
            'record' => ['doktype' => 1],
        ]));
    }

    #[Test]
    public function showsFieldWhenDoktypeIsMissing(): void
    {
        $condition = new HideOnExcludedDoktype(
            new MarkdownExclusionService(),
            $this->createMock(SiteFinder::class),
        );

        self::assertTrue($condition->match([
            'record' => ['uid' => 123, 'pid' => 1],
        ]));
    }
}
