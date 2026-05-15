# AI Bots Love Markdown - Serve TYPO3 pages as Markdown for AI crawlers

This TYPO3 extension provides an alternative Markdown representation
of your pages for AI bots and crawlers.

This makes content accessible not just for humans and screen readers,
but also for AI systems that consume and process web content.

## Features

- Converts any TYPO3 page to Markdown on-the-fly via content negotiation
- Works automatically with your existing page templates - no TypoScript configuration needed
- Extracts content from `<main>` element (or `<body>` as fallback)
- Automatic `<link rel="alternate">` tag for Markdown discovery
- Strips navigation, header, footer, and other non-content elements
- Includes page metadata (title, dates, description, categories) as YAML front matter

## Installation

Use `composer req b13/ai-bots-love-markdown` or install it via TYPO3's Extension Manager.

After installation, add the site set to your site configuration:

```yaml
dependencies:
  - b13/ai-bots-love-markdown
```

## Configuration

### Site Settings

The extension provides two settings that can be configured per site (both enabled by default):

| Setting                                          | Default                                              | Description                                                                                                                                                |
|--------------------------------------------------|------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `ai_bots_love_markdown.enableContentNegotiation` | `true`                                               | Enable content negotiation via `Accept: text/markdown` header                                                                                              |
| `ai_bots_love_markdown.enableDiscoveryTag`       | `true`                                               | Add `<link rel="alternate">` tag to HTML pages for Markdown discovery                                                                                      |
| `ai_bots_love_markdown.pageTypeSuffix`           | `ai-bots-love.md`                                    | PageType Suffix for Markdown link                                                                                                                          |
| `ai_bots_love_markdown.pageTypeTypeNum`          | `2026`                                               | PageType TypeNum for Markdown link                                                                                                                         |
| `ai_bots_love_markdown.removeElements`           | `script style nav footer aside form iframe noscript` | Space-separated HTML tags stripped from the markdown output. `<header>` is intentionally not included — article-level `<header>` regions often hold the H1 |

To override these settings, add them to your site's `settings.yaml`:

```yaml
ai_bots_love_markdown.enableContentNegotiation: true
ai_bots_love_markdown.enableDiscoveryTag: false
ai_bots_love_markdown.pageTypeTypeNum: 1778074315
ai_bots_love_markdown.pageTypeSuffix: 'foo.md'
ai_bots_love_markdown.removeElements: 'script style nav footer aside form iframe noscript header'
```


## Usage

### Access Methods

1. **Accept header**: Request any page with `Accept: text/markdown` header

       curl -H "Accept: text/markdown" https://example.com/my-page/

2. **URL suffix**: Append `/ai-bots-love.md` to any page URL

       https://example.com/ai-bots-love.md

### Output Format

The extension outputs Markdown with YAML front matter, extracting metadata from HTML meta tags
with fallback to TYPO3 page record data:

```markdown
---
title: "From og:title > <title> > page record"
url: "From canonical URL > request URL"
description: "From og:description > meta description > page record"
image: "From og:image (if present)"
author: "From meta author > page record"
date: 2024-01-15
modified: 2024-01-20
keywords:
  - From meta keywords
categories:
  - From TYPO3 categories
type: "From og:type (if present)"
site: "From og:site_name (if present)"
locale: "From og:locale (if present)"
---

# Page Title

Content converted to Markdown...
```

**Metadata Priority:**
- `title`: og:title → `<title>` tag → page record
- `url`: canonical link → request URL
- `description`: og:description → meta description → page record
- `image`: og:image (if present)
- `author`: meta author → page record
- `date`/`modified`: page record (crdate/tstamp)
- `keywords`: meta keywords (if present)
- `categories`: TYPO3 sys_category (from database)

### Auto-Discovery

The extension automatically adds a `<link>` tag to all HTML pages for Markdown discovery:

```html
<link rel="alternate" type="text/markdown" href="https://example.com/my-page/ai-bots-love.md" title="Markdown version" />
```

### Opt-in Behavior

Pages are only converted to Markdown if they contain the `<link rel="alternate" type="text/markdown">` tag.
This means:
- Pages without the site set enabled won't be converted
- You can disable conversion for specific pages by disabling the discovery tag

## Technical Details

### How It Works

1. A PSR-15 middleware intercepts requests with `Accept: text/markdown` header or `/ai-bots-love.md` suffix
2. The normal page rendering proceeds (your existing templates, TypoScript, etc.)
3. The middleware checks if the response contains the markdown alternate link tag
4. If present, it extracts `<main>` content (or `<body>` as fallback)
5. The HTML is converted to Markdown using league/html-to-markdown
6. Navigation, header, footer, and other non-content elements are stripped
7. Page metadata is added as YAML front matter

### Response Headers

The Markdown response includes:

- `Content-Type: text/markdown; charset=utf-8`
- `X-Robots-Tag: noindex` (to prevent indexing of Markdown version)

### Best Practices for Templates

For best results, wrap your main content in a `<main>` element:

```html
<body>
    <header>...</header>
    <nav>...</nav>
    <main>
        <!-- This content will be converted to Markdown -->
        <f:render section="Main" />
    </main>
    <footer>...</footer>
</body>
```

### Excluding Content from Markdown

Beyond the `<main>` selection and the configurable `removeElements` list, you
can mark template regions explicitly via two bundled Fluid partials. They are
auto-registered through the site set, so no `partialRootPaths` setup is
required.

Wrap a region you want **excluded** from the markdown output (teasers,
related-article boxes, breadcrumbs, CTAs):

```html
<f:render partial="MarkdownExclude" contentAs="content">
    <div class="teaser">Read also: …</div>
</f:render>
```

Or **explicitly mark the main content region** (overrides `<main>` detection,
useful when `<main>` is missing or contains too much):

```html
<f:render partial="MarkdownInclude" contentAs="content">
    <article>… real content …</article>
</f:render>
```

Both partials emit HTML comments (`<!-- markdown-start -->`,
`<!-- markdown-exclude-start -->`, …) that survive caching and are stripped
from regular page responses by a frontend middleware before they reach human
visitors. Excludes can be **nested**.

## License

The extension is licensed under GPL v2+, same as the TYPO3 Core.

## Background & Authors

This extension was created by b13 GmbH in 2026 to enable AI systems to better consume website content.
As AI crawlers become increasingly important for content discovery and processing, providing
a clean Markdown representation helps ensure your content is accurately understood and indexed
by AI systems.

Huge credits to Dries Buytaert's inspiration on this topic:
- [The Third Audience](https://dri.es/the-third-audience)
- [RSS Auto-Discovery](https://dri.es/rss-auto-discovery)

[Find more TYPO3 extensions we have developed](https://b13.com/useful-typo3-extensions-from-b13-to-you) that help us deliver value in client projects. As part of the way we work, we focus on testing and best practices to ensure long-term performance, reliability, and results in all our code.
