<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown\EventListener;

use B13\AiBotsLoveMarkdown\Routing\PageTypeMap;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\Event\SiteConfigurationLoadedEvent;
use TYPO3\CMS\Core\Site\SiteSettingsFactory;

/**
 * Event Listener to automatically register the Markdown typeNum (default 2026)
 * and, when llms.txt is enabled, the ".md" and "llms.txt" mappings, so they can
 * be linked to. Only possible if you have a RouteEnhancer of type "PageType".
 */
final class AddMarkdownPageTypeMapping
{
    public function __construct(
        private readonly SiteSettingsFactory $siteSettingsFactory,
    ) {}

    #[AsEventListener(identifier: 'ai-bots-love-markdown/add-pagetype-mapping')]
    public function __invoke(SiteConfigurationLoadedEvent $event): void
    {
        $configuration = $event->getConfiguration();

        // Check if there's a PageType route enhancer
        if (!isset($configuration['routeEnhancers']) || !is_array($configuration['routeEnhancers'])) {
            return;
        }

        foreach ($configuration['routeEnhancers'] as $name => $enhancerConfig) {
            if (!is_array($enhancerConfig)) {
                continue;
            }

            // Check if this is a PageType enhancer
            if (($enhancerConfig['type'] ?? '') !== 'PageType') {
                continue;
            }

            // Check if map exists and our suffix is not already configured
            if (!isset($enhancerConfig['map']) || !is_array($enhancerConfig['map'])) {
                continue;
            }

            $map = PageTypeMap::apply($enhancerConfig['map'], $this->resolveSettings($event->getSiteIdentifier(), $configuration));
            if ($map !== $enhancerConfig['map']) {
                $configuration['routeEnhancers'][$name]['map'] = $map;
                $event->setConfiguration($configuration);
            }
            return;
        }
    }

    /**
     * The event only carries config.yaml. Resolve the settings exactly like
     * TYPO3 does at runtime (site sets, then settings.yaml, else inline
     * settings) so the route map and runtime behaviour always agree.
     */
    private function resolveSettings(string $identifier, array $configuration): array
    {
        return $this->siteSettingsFactory->createSettings(
            is_array($configuration['dependencies'] ?? null) ? $configuration['dependencies'] : [],
            $identifier,
            is_array($configuration['settings'] ?? null) ? $configuration['settings'] : [],
        )->getAll();
    }
}
