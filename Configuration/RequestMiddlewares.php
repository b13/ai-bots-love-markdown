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
        // Strip markdown markers from HTML responses for human visitors.
        //
        // Ordering is load-bearing: this middleware MUST register earlier in the
        // pipeline than MarkdownResponseMiddleware below. The pipeline is a stack
        // — each entry calls $handler->handle() to descend, and post-processes
        // the response on the way back up. Whoever registers later in the
        // pipeline is closer to the controller and therefore strips/modifies
        // the body FIRST on the way up. If strip ran later than the response
        // middleware, MarkdownResponseMiddleware would only ever see HTML with
        // the markers already removed and fall back to <main> extraction.
        'b13/ai-bots-love-markdown/strip-markers' => [
            'target' => \B13\AiBotsLoveMarkdown\Middleware\StripMarkdownMarkersMiddleware::class,
            'after' => [
                'typo3/cms-frontend/prepare-tsfe-rendering',
            ],
            'before' => [
                'b13/ai-bots-love-markdown/response',
            ],
        ],
        // Late middleware: converts HTML response to Markdown (has access to
        // PageInformation). Sits between strip-markers and content-length-headers
        // so it sees the raw HTML (markers still in place) on the way up from
        // the controller.
        'b13/ai-bots-love-markdown/response' => [
            'target' => \B13\AiBotsLoveMarkdown\Middleware\MarkdownResponseMiddleware::class,
            'after' => [
                'b13/ai-bots-love-markdown/strip-markers',
            ],
            'before' => [
                'typo3/cms-frontend/content-length-headers',
            ],
        ],
    ],
];
