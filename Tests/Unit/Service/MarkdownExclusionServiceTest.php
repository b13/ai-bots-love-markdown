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

use B13\AiBotsLoveMarkdown\Service\MarkdownExclusionService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class MarkdownExclusionServiceTest extends UnitTestCase
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

    #[Test]
    public function includesPageWithDefaultContentDoktypeAndNoOverride(): void
    {
        $service = new MarkdownExclusionService();
        $excluded = $service->isExcluded(
            ['doktype' => 1, 'no_markdown_version' => 0],
            $this->siteWithExcludedDoktypes('3,4,6,7,199,254,255'),
        );

        self::assertFalse($excluded);
    }

    #[Test]
    public function excludesPageWhenDoktypeInExcludedList(): void
    {
        $service = new MarkdownExclusionService();
        $excluded = $service->isExcluded(
            ['doktype' => 254, 'no_markdown_version' => 0],
            $this->siteWithExcludedDoktypes('3,4,6,7,199,254,255'),
        );

        self::assertTrue($excluded);
    }

    #[Test]
    public function excludesPageWhenPerPageFlagSet(): void
    {
        $service = new MarkdownExclusionService();
        $excluded = $service->isExcluded(
            ['doktype' => 1, 'no_markdown_version' => 1],
            $this->siteWithExcludedDoktypes('3,4,6,7,199,254,255'),
        );

        self::assertTrue($excluded);
    }

    #[Test]
    public function perPageFlagOverridesEvenWhenDoktypeNotExcluded(): void
    {
        $service = new MarkdownExclusionService();
        $excluded = $service->isExcluded(
            ['doktype' => 1, 'no_markdown_version' => '1'], // string '1' from DB
            $this->siteWithExcludedDoktypes(''),
        );

        self::assertTrue($excluded);
    }

    #[Test]
    public function ignoresEmptySetting(): void
    {
        $service = new MarkdownExclusionService();
        $excluded = $service->isExcluded(
            ['doktype' => 254, 'no_markdown_version' => 0],
            $this->siteWithExcludedDoktypes(''),
        );

        self::assertFalse($excluded);
    }

    #[Test]
    public function ignoresMalformedSettingEntries(): void
    {
        $service = new MarkdownExclusionService();
        // 'abc' and ' ' should be ignored, '254' should still match
        $excluded = $service->isExcluded(
            ['doktype' => 254, 'no_markdown_version' => 0],
            $this->siteWithExcludedDoktypes('abc, ,254, '),
        );

        self::assertTrue($excluded);
    }

    #[Test]
    public function returnsFalseWhenSiteIsNullAndNoPerPageFlag(): void
    {
        $service = new MarkdownExclusionService();
        $excluded = $service->isExcluded(
            ['doktype' => 254, 'no_markdown_version' => 0],
            null,
        );

        self::assertFalse($excluded);
    }

    #[Test]
    public function returnsFalseForMissingDoktype(): void
    {
        $service = new MarkdownExclusionService();
        $excluded = $service->isExcluded(
            [],
            $this->siteWithExcludedDoktypes('3,4,6,7,199,254,255'),
        );

        self::assertFalse($excluded);
    }
}
