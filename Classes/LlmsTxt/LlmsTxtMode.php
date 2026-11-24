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

/**
 * Values of the `pages.llms_txt` field. "Inherit" takes the effective value
 * of the nearest ancestor; the site root behaves as "Include".
 */
enum LlmsTxtMode: int
{
    case Inherit = 0;
    case Include = 1;
    case Optional = 2;
    case Exclude = 3;

    public static function fromRecordValue(mixed $value): self
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return self::Inherit;
        }

        return self::tryFrom((int)$value) ?? self::Inherit;
    }
}
