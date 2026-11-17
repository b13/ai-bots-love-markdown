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

use B13\AiBotsLoveMarkdown\LlmsTxt\LlmsTxtMode;
use B13\AiBotsLoveMarkdown\LlmsTxt\LlmsTxtSettings;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class LlmsTxtSettingsTest extends UnitTestCase
{
    private function siteWithSettings(array $llmsTxtSettings): Site
    {
        $settings = SiteSettings::createFromSettingsTree([
            'ai_bots_love_markdown' => ['llmsTxt' => $llmsTxtSettings],
        ]);

        $site = $this->createMock(Site::class);
        $site->method('getSettings')->willReturn($settings);

        return $site;
    }

    #[Test]
    public function defaultsWhenNothingConfigured(): void
    {
        $settings = LlmsTxtSettings::fromSite($this->siteWithSettings([]));

        self::assertFalse($settings->enabled);
        self::assertSame(2027, $settings->typeNum);
        self::assertSame([], $settings->excludedDoktypes);
        self::assertSame('', $settings->title);
        self::assertSame('', $settings->description);
    }

    #[Test]
    public function readsConfiguredValues(): void
    {
        $settings = LlmsTxtSettings::fromSite($this->siteWithSettings([
            'enabled' => true,
            'typeNum' => 3000,
            'title' => 'My Site',
            'description' => 'About us',
        ]));

        self::assertTrue($settings->enabled);
        self::assertSame(3000, $settings->typeNum);
        self::assertSame('My Site', $settings->title);
        self::assertSame('About us', $settings->description);
    }

    #[Test]
    public function parsesExcludedDoktypes(): void
    {
        $settings = LlmsTxtSettings::fromSite($this->siteWithSettings([
            'excludedDoktypes' => ' 24, 33,x,,',
        ]));

        self::assertSame([24, 33], $settings->excludedDoktypes);
    }

    #[Test]
    public function modeFromRecordValue(): void
    {
        self::assertSame(LlmsTxtMode::Optional, LlmsTxtMode::fromRecordValue('2'));
        self::assertSame(LlmsTxtMode::Exclude, LlmsTxtMode::fromRecordValue(3));
        self::assertSame(LlmsTxtMode::Inherit, LlmsTxtMode::fromRecordValue(null));
        self::assertSame(LlmsTxtMode::Inherit, LlmsTxtMode::fromRecordValue(9));
    }
}
