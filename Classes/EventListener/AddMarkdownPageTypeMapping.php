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

use TYPO3\CMS\Core\Configuration\Event\SiteConfigurationLoadedEvent;

final class AddMarkdownPageTypeMapping
{
    private const TYPE_NUM = 2026;
    private const SUFFIX = 'ai-bots-love-markdown.md';

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

            // Don't add if already configured (either by suffix or typeNum)
            if (isset($enhancerConfig['map'][self::SUFFIX])) {
                continue;
            }
            if (in_array(self::TYPE_NUM, $enhancerConfig['map'], true)) {
                continue;
            }

            // Add our mapping
            $configuration['routeEnhancers'][$name]['map'][self::SUFFIX] = self::TYPE_NUM;
            $event->setConfiguration($configuration);
            return;
        }
    }
}
