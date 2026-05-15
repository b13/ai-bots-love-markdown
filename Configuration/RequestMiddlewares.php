<?php

return [
    'frontend' => [
        // Early middleware: detects markdown requests and cleans the URL before page-resolver
        'b13/ai-bots-love-markdown/request' => [
            'target' => \B13\AiBotsLoveMarkdown\Middleware\MarkdownRequestMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
        // Late middleware: converts HTML response to Markdown (has access to PageInformation)
        'b13/ai-bots-love-markdown/response' => [
            'target' => \B13\AiBotsLoveMarkdown\Middleware\MarkdownResponseMiddleware::class,
            'after' => [
                'typo3/cms-frontend/prepare-tsfe-rendering',
            ],
            'before' => [
                'typo3/cms-frontend/content-length-headers',
            ],
        ],
        // Strip markdown markers from HTML responses for human visitors
        'b13/ai-bots-love-markdown/strip-markers' => [
            'target' => \B13\AiBotsLoveMarkdown\Middleware\StripMarkdownMarkersMiddleware::class,
            'after' => [
                'typo3/cms-frontend/content-length-headers',
            ],
        ],
    ],
];
