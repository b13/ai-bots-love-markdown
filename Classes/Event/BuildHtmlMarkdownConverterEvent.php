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

use League\HTMLToMarkdown\Converter\ConverterInterface;
use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\HtmlConverter;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * Event to modify frontMatter or markdownContent.
 */
final class BuildHtmlMarkdownConverterEvent
{
    protected array $options = [
        'strip_tags' => true,
        'hard_break' => true,
        'remove_nodes' => 'script style nav footer aside header form iframe noscript',
    ];
    protected $converters = [];

    public function __construct(
        public readonly ServerRequestInterface $request,
    ) {
        $this->converters[] = new TableConverter();
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function mergeOptions(array $options): void
    {
        $this->options = array_merge($this->options, $options);
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function setConverters(array $converters): void
    {
        foreach ($converters as $converter) {
            $this->addConverter($converter);
        }
    }

    public function addConverter(ConverterInterface $converter): void
    {
        $this->converters[] = $converter;
    }

    public function getConverters(): array
    {
        return $this->converters;
    }

    public function getHtmlConverter(): HtmlConverter
    {
        $htmlConverter = new HtmlConverter($this->options);
        foreach ($this->converters as $converter) {
            $htmlConverter->getEnvironment()->addConverter($converter);
        }
        return $htmlConverter;
    }
}
