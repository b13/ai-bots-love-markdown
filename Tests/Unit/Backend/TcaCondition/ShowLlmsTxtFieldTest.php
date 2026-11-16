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
use B13\AiBotsLoveMarkdown\Backend\TcaCondition\ShowLlmsTxtField;
use B13\AiBotsLoveMarkdown\Service\MarkdownExclusionService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ShowLlmsTxtFieldTest extends UnitTestCase
{
    private function condition(bool $llmsTxtEnabled = true): ShowLlmsTxtField
    {
        $settings = SiteSettings::createFromSettingsTree([
            'ai_bots_love_markdown' => [
                'excludedDoktypes' => '3,4,254',
                'llmsTxt' => ['enabled' => $llmsTxtEnabled],
            ],
        ]);
        $site = $this->createMock(Site::class);
        $site->method('getSettings')->willReturn($settings);
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);

        return new ShowLlmsTxtField(new HideOnExcludedDoktype(new MarkdownExclusionService(), $siteFinder), $siteFinder);
    }

    #[Test]
    public function showsFieldOnSysfolderEvenIfExcludedFromMarkdown(): void
    {
        self::assertTrue($this->condition()->match(['record' => ['uid' => 5, 'pid' => 1, 'doktype' => 254]]));
    }

    #[Test]
    public function showsFieldOnSysfolderWithFormEngineArrayValue(): void
    {
        self::assertTrue($this->condition()->match(['record' => ['uid' => 5, 'pid' => 1, 'doktype' => ['254']]]));
    }

    #[Test]
    public function hidesFieldOnOtherExcludedDoktype(): void
    {
        self::assertFalse($this->condition()->match(['record' => ['uid' => 5, 'pid' => 1, 'doktype' => 4]]));
    }

    #[Test]
    public function showsFieldOnStandardPage(): void
    {
        self::assertTrue($this->condition()->match(['record' => ['uid' => 5, 'pid' => 1, 'doktype' => 1]]));
    }

    #[Test]
    public function hidesFieldWhenLlmsTxtDisabledForSite(): void
    {
        self::assertFalse($this->condition(false)->match(['record' => ['uid' => 5, 'pid' => 1, 'doktype' => 1]]));
        self::assertFalse($this->condition(false)->match(['record' => ['uid' => 5, 'pid' => 1, 'doktype' => 254]]));
    }
}
