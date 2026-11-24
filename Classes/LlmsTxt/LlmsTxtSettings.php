<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown\LlmsTxt;

use TYPO3\CMS\Core\Site\Entity\Site;

final readonly class LlmsTxtSettings
{
    public const DEFAULT_TYPE_NUM = 2027;
    private const PREFIX = 'ai_bots_love_markdown.llmsTxt.';

    /**
     * @param int[] $excludedDoktypes
     */
    public function __construct(
        public bool $enabled,
        public int $typeNum,
        public array $excludedDoktypes,
        public string $title,
        public string $description,
    ) {}

    public static function fromSite(Site $site): self
    {
        $settings = $site->getSettings();

        return new self(
            (bool)$settings->get(self::PREFIX . 'enabled', false),
            (int)$settings->get(self::PREFIX . 'typeNum', self::DEFAULT_TYPE_NUM),
            self::parseIntList((string)$settings->get(self::PREFIX . 'excludedDoktypes', '')),
            trim((string)$settings->get(self::PREFIX . 'title', '')),
            trim((string)$settings->get(self::PREFIX . 'description', '')),
        );
    }

    /**
     * @return int[]
     */
    private static function parseIntList(string $raw): array
    {
        $ids = [];
        foreach (explode(',', $raw) as $part) {
            $trimmed = trim($part);
            if ($trimmed !== '' && ctype_digit($trimmed)) {
                $ids[] = (int)$trimmed;
            }
        }

        return $ids;
    }
}
