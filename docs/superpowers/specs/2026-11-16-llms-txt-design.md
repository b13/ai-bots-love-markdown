# llms.txt generation — Design

Status: draft for review · 2026-11-16

## Intent

`ai-bots-love-markdown` already serves every page as Markdown. What agents are
missing is an entry point: a short, curated index that tells them which pages
matter, how the site is structured, and what each page is about — without
loading every page first. That is what `/llms.txt` (llmstxt.org) is for.

The extension gets an optional feature that generates `/llms.txt` per site
language from the page tree and links to the existing Markdown versions of the
pages. In effect: a sitemap for agents, with structure, per-link notes and a
simple weighting (order + `## Optional`). The page content itself stays in the
`.md` responses.

Typical target: a mid-sized site with several hundred pages, including many
detail pages (news, people, events) that should not end up in llms.txt.

## Goals

- `/llms.txt` and `/<lang-base>/llms.txt` per site language, generated from the
  page tree, following the llmstxt.org format.
- Every link points to the page's Markdown version.
- Sensible output without editorial work; editors only handle exceptions.
- When the feature is on, Markdown URLs become `<page-path>.md`
  (e.g. `/study/student-advisory.md`) everywhere: discovery tag, llms.txt,
  incoming requests.
- Off by default; existing installations see no change until they enable it.
- Always up to date (no manual cache clearing / regeneration step).

## Non-goals

- `llms-full.txt` (all Markdown content in one file) — possible follow-up.
- `ai-catalog.json` / Agentic Resource Discovery.
- robots.txt or `<link>` hints pointing to `/llms.txt`.
- A separate "note for agents" text field per page (page `description` is used).
- Depth limits for inclusion (explicitly rejected: important pages such as a
  single study programme often sit deep in the tree).

## Anti-goals

- An llms.txt that is just a dump of every URL (a second sitemap.xml).
- Breaking existing `…/ai-bots-love.md` URLs that bots already know.
- Search pages, verification/thank-you pages and test pages in the output.

## Approaches considered

- **A — PageType of the site root (chosen).** `llms.txt` is a dedicated
  `typeNum` rendered via TypoScript `PAGE` on the root page and mapped through
  the site's `PageType` route enhancer to `/llms.txt` (same mechanism the
  extension already uses for Markdown, and EXT:seo uses for `sitemap.xml`).
  Language handling and TYPO3 page caching come for free; invalidation via
  cache tags.
- **B — Own middleware intercepting `/llms.txt`.** Works without a route
  enhancer but needs own language resolution and caching. More code, same
  result.
- **C — Static file written on cache clear** (as in `rtfirst/llms-txt`).
  Rejected: awkward with multiple languages and deployments, stale between
  cache clears.

## Settings (site set `b13/ai-bots-love-markdown`)

| Setting | Type | Default | Meaning |
|---|---|---|---|
| `ai_bots_love_markdown.llmsTxt.enabled` | bool | `false` | Generate llms.txt and switch Markdown URLs to `<path>.md` |
| `ai_bots_love_markdown.llmsTxt.typeNum` | int | `2027` | typeNum of the llms.txt page type |
| `ai_bots_love_markdown.llmsTxt.excludedDoktypes` | string | `''` | Extra doktypes never listed in llms.txt (e.g. news, person or event detail pages) in addition to `excludedDoktypes` |
| `ai_bots_love_markdown.llmsTxt.title` | string | `''` | H1; empty → root page title |
| `ai_bots_love_markdown.llmsTxt.description` | string | `''` | Blockquote summary; empty → root page `description` |

Settings are editable in the backend (Sites → Settings), which is what
"fixed as a backend option" means here.

## Page property (new TCA field on `pages`)

Field `llms_txt` (select), placed next to `markdown_version`:

- **Inherit** (default) — use the value of the nearest ancestor that sets one;
  the site root effectively has "Include"
- **Include**
- **Include under "Optional"**
- **Do not include**

The value **inherits down the subtree**: setting a sysfolder such as "Footer"
or "Landing pages" to "Do not include" removes all its pages with one click; a
single page inside it can be set back to "Include". Setting a level-1 page to
"Optional" moves its whole section to `## Optional`, except descendants that
set their own value. (Reason: many sites keep level-1 sysfolders with internal names — secondary
menus, landing pages, footer and meta navigation — that would otherwise become
sections and could only be removed page by page.)

Visibility: the field is shown on standard pages **and on sysfolders**
(doktype 254), because sysfolders are where subtree decisions are made.
It stays hidden on other doktypes in `excludedDoktypes`. This means the field
needs its own `displayCond` rather than reusing `HideOnExcludedDoktype` as is.

`l10n_mode: exclude` (inclusion is a structural decision, same for all
languages).

## Which pages are listed

A page is listed unless any of these applies:

1. Doktype is in `excludedDoktypes` or `llmsTxt.excludedDoktypes`.
2. `no_search = 1` ("Exclude from search") or `no_index = 1` (SEO noindex).
3. `markdown_version = 0` (no Markdown version → nothing to link to).
4. Effective `llms_txt` value (own or inherited) is "Do not include".
5. Not visible in the frontend for anonymous users (hidden, start/end time,
   `fe_group` restricted) or not available in the requested language.

Rules 1–3 and 5 are hard rules per page: they cannot be overridden by the
`llms_txt` field and do not affect children (a sysfolder excluded by doktype
still passes its `llms_txt` value down). Only rule 4 inherits.

## Output structure

```markdown
# <title setting | root page title>

> <description setting | root page description>

## <level-1 page title>

- [<level-1 page title>](https://…/about-us.md): <description>
- [<descendant title>](https://…/about-us/locations.md): <description>
- …

## <next level-1 page title>
…

## Optional

- [<title>](https://…/press.md): <description>
```

- Sections = pages on level 1 below the site root, in tree order.
- All listed descendants of a level-1 page (any depth) go into its section,
  flattened, in tree order (depth-first).
- The level-1 page itself is the first link of its section if it is listed;
  if it is not listed (e.g. a sysfolder), its title is still the heading.
- Sections without links are omitted.
- Pages whose effective value is "Optional" go into one `## Optional`
  section at the end instead of their own section.
- Link note: page `description`; fall back to `abstract`; omit `: …` if both
  are empty.
- Links are absolute URLs built via TYPO3 link generation with the Markdown
  typeNum (so they follow the `.md` rule below).

## Markdown URL format when llms.txt is enabled

- `AddMarkdownPageTypeMapping` adds `'.md' => <markdown typeNum>` as the
  **first** entry of the PageType `map` (TYPO3 generates with the first
  matching key; keys starting with `.` are appended to the page path →
  `/study/student-advisory.md`). The old suffix (`pageTypeSuffix`) stays in
  the map so old URLs still resolve. `pageTypeSuffix` is otherwise ignored
  while llms.txt is enabled.
- It also adds `'llms.txt' => llmsTxt.typeNum` to the map.
- `MarkdownRequestMiddleware` must recognise and strip `<path>.md` (today it
  only handles `/<suffix>`), including the root page and language prefixes
  (`/index.md`, `/en/index.md`), in addition to the old form.
- The discovery `<link rel="alternate">` keeps using typolink with the
  Markdown typeNum, so it picks up the new form automatically.
- **Root page:** with `index: ''` in the enhancer (a common setup) TYPO3
  would generate `/.md`. Requirement documented in the README: the PageType
  enhancer needs `index: 'index'` → root becomes `/index.md`. The request
  middleware still accepts `/.md` defensively. Verify together with
  trailing-slash handling extensions such as `b13/de-slash`.

## Delivery and caching

- TypoScript `PAGE` object for `llmsTxt.typeNum`, only active when
  `llmsTxt.enabled` is true; output via a PHP renderer (USER), no HTML
  wrapping, `Content-Type: text/plain; charset=utf-8` (llmstxt.org convention;
  builder may choose `text/markdown` if there is a reason).
- Requested on a page other than the site root → 404 (one canonical file per
  language).
- Cached in the TYPO3 page cache; adds `pageId_<uid>` cache tags for every
  traversed page so editing/moving/adding pages invalidates it.
- The Markdown response middleware must not touch llms.txt responses.

## Testing

- Unit tests in the extension for inclusion rules (incl. inheritance) and
  output rendering, following the existing `Tests/Unit` style.
- Manual check in a TYPO3 13.4 integration project with multiple languages:
  enable the site set and `llmsTxt.enabled`, configure
  `llmsTxt.excludedDoktypes`, set "Do not include" on a few sysfolders and
  check `/llms.txt` and `/<lang>/llms.txt`, that `.md` links resolve, that old
  `ai-bots-love.md` URLs still work and that the search page (`no_search` /
  `no_index`) is absent.

## Left to the builder

- Class/field/label naming, XLIFF labels (en + de).
- Exact traversal implementation (PageRepository / QueryBuilder with frontend
  restrictions and language overlays).
- README section for the feature.
