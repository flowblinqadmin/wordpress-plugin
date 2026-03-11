# Flowblinq WordPress Plugins

Monorepo for Flowblinq WordPress plugins.

## Plugins

| Plugin | Directory | Description |
|--------|-----------|-------------|
| [Flowblinq GEO](flowblinq-geo/) | `flowblinq-geo/` | AI visibility optimization — proxy-serves GEO files and injects schema markup |

## Repository Structure

```
flowblinq-geo/
  flowblinq-geo.php        # Plugin bootstrap (activation/deactivation hooks)
  uninstall.php             # Clean removal of all options and transients
  includes/
    constants.php           # Configurable defaults (timeouts, TTLs, size limits)
    class-proxy.php         # Rewrite rules, proxy handler, schema injection, robots.txt
    class-api-client.php    # OAuth + REST client for geo.flowblinq.com
    class-admin-page.php    # Settings page, audit page, AJAX handlers
  assets/
    admin.css               # Admin UI styles
    admin.js                # Audit polling, test connection, clear cache
  languages/
    flowblinq-geo.pot       # Translation template
  tests/
    bootstrap.php           # Standalone WordPress function stubs
    test-proxy.php          # 30 unit tests (proxy, admin, uninstall)
```

## Dependencies

- **WordPress** 6.0+
- **PHP** 7.4+ (typed properties in `class-api-client.php`)
- **Pretty Permalinks** enabled (any structure except "Plain")
- **Flowblinq account** — Client ID + Secret from [geo.flowblinq.com](https://geo.flowblinq.com/dashboard/settings)
- No external PHP libraries. Uses WordPress HTTP API (`wp_remote_*`) exclusively.

## How It Works

### Proxy Architecture

The plugin registers WordPress rewrite rules that intercept requests to well-known GEO paths and proxies them to `geo.flowblinq.com/api/serve/{slug}/*`:

| Local Path | Upstream Path | Content-Type |
|------------|---------------|--------------|
| `/llms.txt` | `/{slug}/llms.txt` | `text/plain` |
| `/llms-full.txt` | `/{slug}/llms-full.txt` | `text/plain` |
| `/.well-known/ucp.json` | `/{slug}/business.json` | `application/json` |

Responses are cached using WordPress Transients (1hr TTL by default). No files are stored on disk.

### Schema JSON-LD Injection

On every page load, the plugin injects `<script type="application/ld+json">` tags into `<head>` with structured data fetched from `/{slug}/schema.json`. Uses stale-while-revalidate caching to avoid blocking page rendering on cache expiry.

### Robots.txt Directives

Appends `Allow` directives for GPTBot, ClaudeBot, and PerplexityBot to the WordPress-generated `robots.txt`. Respects the "Discourage search engines" setting — directives are omitted when that option is enabled.

### GEO Audit

Admin UI at **Tools > GEO Audit** submits the site URL to the Flowblinq API, polls for completion, and displays a GEO visibility score. A "Verify" button triggers a second audit to show before/after comparison.

### Admin Settings

**Settings > Flowblinq GEO** provides:
- Client ID / Client Secret fields (secret masked after save)
- Site slug display (auto-populated from first audit)
- Test Connection button (verifies upstream proxy is reachable)
- Clear Cache button (purges all proxy transients)

## Configuration

All defaults are defined in `includes/constants.php` and can be overridden by defining the constant before the plugin loads (e.g., in `wp-config.php`):

| Constant | Default | Description |
|----------|---------|-------------|
| `FQGEO_SERVE_BASE` | `https://geo.flowblinq.com/api/serve` | Upstream base URL |
| `FQGEO_PROXY_TIMEOUT` | `10` (seconds) | HTTP timeout for upstream requests |
| `FQGEO_PROXY_MAX_SIZE` | `524288` (512 KB) | Max response body size |
| `FQGEO_CACHE_TTL` | `3600` (1 hour) | Transient cache lifetime |
| `FQGEO_TOKEN_TTL` | `3500` (seconds) | OAuth token cache lifetime |
| `FQGEO_MAX_POLLS` | `120` | Max polling attempts for audit status |

## Security

- Client secret masked in admin UI after save
- Per-action nonce verification on all AJAX endpoints
- `manage_options` capability check on all admin actions
- Hardcoded upstream base URL (no user-configurable SSRF surface)
- `rawurlencode()` on slug in all URL construction
- Slug format validated with `/^[a-z0-9\-]+$/i` at storage time
- `JSON_HEX_TAG` encoding in schema output (XSS prevention)
- Proxy response size capped at 512 KB
- Plugin-prefixed exception class to avoid global namespace collisions

## Testing

Tests are standalone PHP — no PHPUnit or WordPress test harness required:

```bash
cd flowblinq-geo
php tests/test-proxy.php
```

30 tests covering proxy endpoints, schema injection, robots.txt, admin AJAX handlers, and uninstall cleanup.

## Uninstall

Deleting the plugin (not just deactivating) removes all stored data:
- Options: `fq_client_id`, `fq_client_secret`, `fq_site_slug`, `fq_active_audit_id`
- Transients: `fq_access_token`, `fq_proxy_*`
- Rewrite rules flushed

## License

GPL v2 or later. See [LICENSE](LICENSE).
