# Custom Author Box

An Elementor author box with managed authors, four layouts, and Yoast SEO author tags.
Authors are managed independently of WordPress user accounts, so guest writers and
organizations can be credited without creating logins or using Gravatar.

**Requires:** WordPress 6.5+, PHP 8.1+, Elementor 3.5+. Yoast SEO is optional.

## Using it

1. **Settings → Custom Author Box**: add authors (name, image, short bio, optional posts link and social profiles).
2. On a post, pick the author in the **Custom Author Box** panel in the sidebar.
3. In Elementor, add the **Custom Author Box** widget (usually to a single-post template) and choose a layout.

### Layouts

| Layout | Description |
|---|---|
| Classic | Image left, text right. Stacks on phones. |
| Centered | Image on top, everything centered. |
| Compact | Small image with name and link on one line. A byline. |
| Card | Coloured header band with the image overlapping it. |

All colours, fonts, spacing, borders and image size are in the widget's **Style** tab.
The widget can also show **a specific author** instead of the post's author (Content → Author).

### SEO (Yoast)

On posts with a custom author, Yoast's output uses that author:

| Tag | Value |
|---|---|
| `<meta name="author">` | Author name |
| `article:author` | Author's Facebook URL (omitted if none) |
| `twitter:creator` | Author's X handle (falls back to the site's X account) |
| `twitter:label1` / `twitter:data1` | "Written by" author name (Slack, X, Discord previews) |
| Schema.org `author` | Person with name, image, bio, posts link and `sameAs` profiles |

Posts without a custom author are untouched.

## Developer notes

- Filters: `custom_author_box_post_types` (default `['post']`), `custom_author_box_capability` (default `manage_options`).
- Post meta key: `_custom_author_box_author` (author ID).
- Table: `{prefix}custom_authors`. Upgrades run automatically and never drop columns.
- Author lookups use the object cache (works with Redis/Memcached).
- The widget declares `is_dynamic_content()` so Elementor's element cache never serves one post's author on another.

## Changelog

### 3.0.0 — October 31, 2026
- Four layouts: Classic, Centered, Compact, Card, each fully styleable.
- Optional social profiles per author: X, Facebook, LinkedIn, website. Shown as icons (optional) and used in Open Graph, X card and schema tags.
- Widget can show the post's author or a specific author; editor preview shows a real author.
- Media Library image picker with responsive `srcset`; author images are lazy-loaded at low priority.
- Security: all writes are POST-only via `admin-post.php` with per-action (and per-author for delete) nonces and capability checks; strict validation of every URL field (http/https only, domain-checked social links); redirect-after-save so refreshing never re-submits.
- Deleting an author unassigns them from posts.
- Rewritten for PHP 8.1+ (namespaces, enums, readonly properties, strict types); shows a notice instead of crashing on older PHP.
- Uses Elementor's optimized markup and current widget APIs.

### 2.1 — October 30, 2026
- Yoast SEO integration for author meta, "Written by" previews and schema.

### 2.0 — October 30, 2026
- Simplified to name, image, bio; "About the Author" heading; Elementor style controls; nonces and capability checks.

### June 2024
- Database table for authors; settings page; metabox; first Elementor widget.
