<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown\Event;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * Event to modify the YAML front matter data array before it is rendered.
 *
 * Listeners can add, remove, or replace entries in the $frontMatter array.
 * The array preserves insertion order, which is reflected in the rendered YAML.
 */
final class ModifyFrontMatterDataEvent
{
    public function __construct(
        public array $frontMatter,
        public readonly array $pageRecord,
        public readonly string $html,
        public readonly ?PageInformation $pageInformation,
        public readonly ?ServerRequestInterface $request,
    ) {}
}
