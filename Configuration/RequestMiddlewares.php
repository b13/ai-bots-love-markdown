<?php

return [
    'frontend' => [
        'b13/ai-bots-love-markdown/url-rewrite' => [
            'target' => \B13\AiBotsLoveMarkdown\Middleware\MarkdownUrlRewriteMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
