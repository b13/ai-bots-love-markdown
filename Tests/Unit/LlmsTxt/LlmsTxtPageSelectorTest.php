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
use B13\AiBotsLoveMarkdown\LlmsTxt\LlmsTxtPageSelector;
use B13\AiBotsLoveMarkdown\LlmsTxt\LlmsTxtSettings;
use B13\AiBotsLoveMarkdown\LlmsTxt\LlmsTxtStructure;
use B13\AiBotsLoveMarkdown\Service\MarkdownExclusionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class LlmsTxtPageSelectorTest extends UnitTestCase
{
    private function select(array $level1Pages, array $llmsTxtExcludedDoktypes = []): LlmsTxtStructure
    {
        $siteSettings = SiteSettings::createFromSettingsTree([
            'ai_bots_love_markdown' => ['excludedDoktypes' => '3,4,6,7,199,254,255'],
        ]);
        $site = $this->createMock(Site::class);
        $site->method('getSettings')->willReturn($siteSettings);

        $settings = new LlmsTxtSettings(true, 2027, $llmsTxtExcludedDoktypes, '', '');

        return (new LlmsTxtPageSelector(new MarkdownExclusionService()))->select($level1Pages, $site, $settings);
    }

    private static function page(int $uid, string $title, array $overrides = [], array $children = []): array
    {
        return array_merge([
            'uid' => $uid,
            'title' => $title,
            'doktype' => 1,
            'no_search' => 0,
            'no_index' => 0,
            'markdown_version' => 1,
            'llms_txt' => 0,
            'description' => '',
            'abstract' => '',
            'children' => $children,
        ], $overrides);
    }

    /**
     * @param list<LlmsTxtEntry> $entries
     * @return list<string>
     */
    private static function titles(array $entries): array
    {
        return array_map(static fn(LlmsTxtEntry $entry): string => $entry->title, $entries);
    }

    #[Test]
    public function listsDeepPageUnderItsLevel1Section(): void
    {
        $structure = $this->select([
            self::page(10, 'Programs', [], [
                self::page(11, 'Bachelor', [], [
                    self::page(12, 'Master'),
                ]),
            ]),
            self::page(20, 'About'),
        ]);

        self::assertCount(2, $structure->sections);
        self::assertSame('Programs', $structure->sections[0]->title);
        self::assertSame(['Programs', 'Bachelor', 'Master'], self::titles($structure->sections[0]->entries));
        self::assertSame('About', $structure->sections[1]->title);
        self::assertSame([], $structure->optional);
    }

    #[Test]
    public function excludeOnFolderInheritsToChildren(): void
    {
        $structure = $this->select([
            self::page(10, 'Footer', ['doktype' => 254, 'llms_txt' => 3], [
                self::page(11, 'Imprint'),
            ]),
        ]);

        self::assertSame([], $structure->sections);
        self::assertSame([], $structure->optional);
    }

    #[Test]
    public function childCanReincludeInsideExcludedFolder(): void
    {
        $structure = $this->select([
            self::page(10, 'Landing pages', ['doktype' => 254, 'llms_txt' => 3], [
                self::page(11, 'Campaign'),
                self::page(12, 'Important landing page', ['llms_txt' => 1], [
                    self::page(13, 'Inherits include'),
                ]),
            ]),
        ]);

        self::assertCount(1, $structure->sections);
        self::assertSame('Landing pages', $structure->sections[0]->title);
        self::assertSame(['Important landing page', 'Inherits include'], self::titles($structure->sections[0]->entries));
    }

    #[Test]
    public function excludedLevel1FolderStillProvidesHeading(): void
    {
        $structure = $this->select([
            self::page(10, 'Service', ['doktype' => 254], [
                self::page(11, 'Contact'),
            ]),
        ]);

        self::assertCount(1, $structure->sections);
        self::assertSame('Service', $structure->sections[0]->title);
        self::assertSame(['Contact'], self::titles($structure->sections[0]->entries));
    }

    #[Test]
    public function optionalInheritsAndMovesToOptional(): void
    {
        $structure = $this->select([
            self::page(10, 'Press', ['llms_txt' => 2], [
                self::page(11, 'Press release'),
                self::page(12, 'Press kit', ['llms_txt' => 1]),
            ]),
        ]);

        self::assertCount(1, $structure->sections);
        self::assertSame(['Press kit'], self::titles($structure->sections[0]->entries));
        self::assertSame(['Press', 'Press release'], self::titles($structure->optional));
    }

    public static function hardRuleProvider(): array
    {
        return [
            'no_index' => [['no_index' => 1]],
            'no_search' => [['no_search' => 1]],
            'markdown disabled' => [['markdown_version' => 0]],
            'llms.txt excluded doktype' => [['doktype' => 24]],
            'markdown excluded doktype' => [['doktype' => 4]],
            'not available in requested language' => [[LlmsTxtPageSelector::UNAVAILABLE_IN_LANGUAGE => true]],
        ];
    }

    #[Test]
    #[DataProvider('hardRuleProvider')]
    public function hardRulesNotOverridable(array $overrides): void
    {
        $structure = $this->select([
            self::page(10, 'Section', [], [
                self::page(11, 'Hard excluded', array_merge(['llms_txt' => 1], $overrides), [
                    self::page(12, 'Child still listed'),
                ]),
            ]),
        ], [24]);

        self::assertSame(['Section', 'Child still listed'], self::titles($structure->sections[0]->entries));
    }

    public static function hiddenSubtreeDoktypeProvider(): array
    {
        return [
            'backend user section' => [6],
            'recycler' => [255],
        ];
    }

    #[Test]
    #[DataProvider('hiddenSubtreeDoktypeProvider')]
    public function subtreeOfHiddenDoktypeInheritsExclude(int $doktype): void
    {
        $structure = $this->select([
            self::page(10, 'Test pages', ['doktype' => $doktype], [
                self::page(11, 'Inherits', [], [
                    self::page(12, 'Inherits too'),
                ]),
            ]),
        ]);

        self::assertSame([], $structure->sections);
    }

    #[Test]
    #[DataProvider('hiddenSubtreeDoktypeProvider')]
    public function explicitIncludeBelowHiddenDoktypeIsListed(int $doktype): void
    {
        $structure = $this->select([
            self::page(10, 'Test pages', ['doktype' => $doktype], [
                self::page(11, 'Inherits'),
                self::page(12, 'Explicitly included', ['llms_txt' => 1], [
                    self::page(13, 'Inherits include'),
                ]),
            ]),
        ]);

        self::assertCount(1, $structure->sections);
        self::assertSame(['Explicitly included', 'Inherits include'], self::titles($structure->sections[0]->entries));
    }

    #[Test]
    public function noteFallsBackToAbstract(): void
    {
        $structure = $this->select([
            self::page(10, 'With description', ['description' => ' Desc ', 'abstract' => 'Abstract']),
            self::page(20, 'With abstract', ['description' => '  ', 'abstract' => 'A']),
            self::page(30, 'Without note'),
        ]);

        self::assertSame('Desc', $structure->sections[0]->entries[0]->note);
        self::assertSame('A', $structure->sections[1]->entries[0]->note);
        self::assertSame('', $structure->sections[2]->entries[0]->note);
        self::assertSame(20, $structure->sections[1]->entries[0]->uid);
    }
}
