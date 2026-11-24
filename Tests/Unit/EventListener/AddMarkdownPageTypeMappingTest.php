<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown\Tests\Unit\EventListener;

use B13\AiBotsLoveMarkdown\EventListener\AddMarkdownPageTypeMapping;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\Event\SiteConfigurationLoadedEvent;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Core\Site\SiteSettingsFactory;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class AddMarkdownPageTypeMappingTest extends UnitTestCase
{
    private static function configuration(): array
    {
        return [
            'rootPageId' => 1,
            'dependencies' => ['vendor/site-package'],
            'settings' => ['inline' => 'value'],
            'routeEnhancers' => [
                'PageTypeSuffix' => ['type' => 'PageType', 'map' => ['sitemap.xml' => 1533906435]],
            ],
        ];
    }

    private function mapFor(array $resolvedSettingsTree): array
    {
        $factory = $this->createMock(SiteSettingsFactory::class);
        // Same resolution TYPO3 uses at runtime: sets, then settings.yaml, else inline settings.
        $factory->expects(self::once())
            ->method('createSettings')
            ->with(['vendor/site-package'], 'main', ['inline' => 'value'])
            ->willReturn(SiteSettings::createFromSettingsTree($resolvedSettingsTree));

        $event = new SiteConfigurationLoadedEvent('main', self::configuration());
        (new AddMarkdownPageTypeMapping($factory))($event);

        return $event->getConfiguration()['routeEnhancers']['PageTypeSuffix']['map'];
    }

    #[Test]
    public function usesRuntimeSiteSettingsResolution(): void
    {
        $map = $this->mapFor(['ai_bots_love_markdown' => ['llmsTxt' => ['enabled' => true, 'typeNum' => 3000]]]);

        self::assertSame(['.md', 'sitemap.xml', 'ai-bots-love.md', 'llms.txt'], array_keys($map));
        self::assertSame(3000, $map['llms.txt']);
    }

    #[Test]
    public function keepsLegacyMappingWhenDisabled(): void
    {
        $map = $this->mapFor(['ai_bots_love_markdown' => ['llmsTxt' => ['enabled' => false]]]);

        self::assertSame(['sitemap.xml' => 1533906435, 'ai-bots-love.md' => 2026], $map);
    }
}
