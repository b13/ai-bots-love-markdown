<?php

return [
    'frontend' => [
        // Response transformer: converts HTML to Markdown for Accept: text/markdown requests
        'b13/ai-bots-love-markdown/response' => [
            'target' => \B13\AiBotsLoveMarkdown\Middleware\MarkdownResponseMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
