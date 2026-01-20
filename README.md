# AI Bots Love Markdown - Serve TYPO3 pages as Markdown for AI crawlers

This TYPO3 extension provides an alternative Markdown representation of your pages for AI bots and crawlers,
inspired by Dries Buytaert's concept of the "third audience" - making content accessible not just for humans
and screen readers, but also for AI systems that consume and process web content.

## Features

- Renders any TYPO3 page as clean Markdown with YAML front matter
- Multiple access methods: URL suffix, query parameter, or content negotiation via Accept header
- Automatic `<link rel="alternate">` tag for Markdown discovery
- Converts HTML content elements to Markdown using league/html-to-markdown
- Strips navigation, footer, and other non-content elements
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

| Setting | Default | Description |
|---------|---------|-------------|
| `ai_bots_love_markdown.enableContentNegotiation` | `true` | Enable content negotiation via `Accept: text/markdown` header |
| `ai_bots_love_markdown.enableDiscoveryTag` | `true` | Add `<link rel="alternate">` tag to HTML pages for Markdown discovery |

To override these settings, add them to your site's `config.yaml`:

```yaml
settings:
  ai_bots_love_markdown:
    enableContentNegotiation: true
    enableDiscoveryTag: false
```

### Route Enhancer (automatic)

If your site has a `PageType` route enhancer configured, the extension automatically adds
`ai-bots-love-markdown.md: 2026` to the map. No manual configuration required.

### Column Positions (TypoScript)

By default, the extension renders content from all column positions defined in the page's backend layout,
with colPos 0 always rendered first.

To override this behavior, configure specific columns in TypoScript:

```typoscript
markdown_page.10.colPos = 0
# Or multiple columns:
markdown_page.10.colPos = 0,1,2
```

Leave empty (default) to automatically use all columns from the backend layout.

## Usage

### Access Methods

1. **Query parameter**: Append `?type=2026` to any page URL

       https://example.com/my-page/?type=2026

2. **URL suffix**: Use `/ai-bots-love-markdown.md` suffix (automatic if PageType enhancer exists)

       https://example.com/my-page/ai-bots-love-markdown.md

3. **Accept header**: Request with `Accept: text/markdown` header

       curl -H "Accept: text/markdown" https://example.com/my-page/

### Output Format

The extension outputs Markdown with YAML front matter:

```markdown
---
title: My Page Title
url: https://example.com/my-page/
date: 2024-01-15
modified: 2024-01-20
description: Page description from TYPO3
categories:
  - Category 1
  - Category 2
---

# My Page Title

Content converted to Markdown...
```

### Auto-Discovery

The extension automatically adds a `<link>` tag to all HTML pages for Markdown discovery:

```html
<link rel="alternate" type="text/markdown" href="https://example.com/my-page/?type=2026" title="Markdown version" />
```

## Technical Details

### How It Works

1. A middleware handles `Accept: text/markdown` content negotiation
2. A PAGE object with `typeNum=2026` renders the Markdown output
3. Content elements from all backend layout columns are rendered as HTML (colPos 0 first), then converted to Markdown
4. Navigation, footer, forms, and other non-content elements are stripped
5. Page metadata is extracted and formatted as YAML front matter

### Response Headers

The Markdown response includes:

- `Content-Type: text/markdown; charset=utf-8`
- `X-Robots-Tag: noindex` (to prevent indexing of Markdown version)

## License

The extension is licensed under GPL v2+, same as the TYPO3 Core.

## Background & Authors

This extension was created by b13 GmbH to enable AI systems to better consume website content.
As AI crawlers become increasingly important for content discovery and processing, providing
a clean Markdown representation helps ensure your content is accurately understood and indexed
by AI systems.

[Find more TYPO3 extensions we have developed](https://b13.com/useful-typo3-extensions-from-b13-to-you) that help us deliver value in client projects. As part of the way we work, we focus on testing and best practices to ensure long-term performance, reliability, and results in all our code.
