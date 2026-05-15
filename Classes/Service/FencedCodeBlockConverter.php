<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 CMS-based extension "ai_bots_love_markdown" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace B13\AiBotsLoveMarkdown\Service;

use League\HTMLToMarkdown\Converter\ConverterInterface;
use League\HTMLToMarkdown\ElementInterface;

class FencedCodeBlockConverter implements ConverterInterface
{
    public function convert(ElementInterface $element): string
    {
        $innerHTML = $element->getChildrenAsString();

        $language = '';
        if (preg_match('/<code[^>]+class="[^"]*(?:language-|lang-)([a-zA-Z0-9_+#-]+)/i', $innerHTML, $matches)) {
            $language = $matches[1];
        }

        $code = strip_tags($innerHTML);
        $code = html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $code = rtrim($code);

        return "\n\n```{$language}\n{$code}\n```\n\n";
    }

    public function getSupportedTags(): array
    {
        return ['pre'];
    }
}
