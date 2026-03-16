=== Flowblinq GEO ===
Contributors: flowblinq
Tags: seo, ai, llm, schema, optimization
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI visibility optimization for your WordPress site — powered by Flowblinq GEO.

== Description ==

Flowblinq GEO makes your site visible to AI engines (ChatGPT, Claude, Perplexity) by serving standardized GEO files and injecting structured data — all automatically.

**What it does:**

* Serves `/llms.txt` and `/llms-full.txt` so AI models can discover and cite your content
* Serves `/.well-known/ucp.json` (business profile for AI consumption)
* Injects Schema.org JSON-LD markup into every page's `<head>`
* Adds `robots.txt` directives that invite AI crawlers to index your GEO files
* Provides a one-click GEO audit with a visibility score and actionable recommendations
* **Captures true traffic sources** — reads the HTTP Referer header server-side so LinkedIn, Twitter, and email referrals show up correctly in your analytics (these are stripped by browsers before JavaScript runs)

**How it works:**

The plugin acts as a thin proxy. It registers WordPress rewrite rules for the GEO file paths, then serves content fetched from the Flowblinq platform. Responses are cached locally (1 hour) using WordPress Transients — no files are written to disk.

Requires a free Flowblinq account. Get your API credentials at [geo.flowblinq.com](https://geo.flowblinq.com/dashboard/settings).

== Installation ==

1. Upload the `flowblinq-geo` folder to `/wp-content/plugins/`.
2. Activate the plugin in **WP Admin > Plugins**.
3. Go to **Settings > Flowblinq GEO** and enter your Client ID and Client Secret.
4. **Important:** Your site must use "pretty permalinks" (any structure except "Plain"). Go to **Settings > Permalinks** if you haven't set this up.
5. Go to **Tools > GEO Audit** and click **Run Free Audit** to submit your site.
6. Once the audit completes, your GEO files are served automatically.

== Frequently Asked Questions ==

= Where do I get my API credentials? =

Sign up at [geo.flowblinq.com](https://geo.flowblinq.com), then go to **Settings > API** to generate a Client ID and Client Secret.

= How long does an audit take? =

Typically 2-5 minutes depending on site size. The plugin polls automatically and shows a progress bar.

= What URLs does the plugin create? =

After your first audit, the plugin serves:

* `yoursite.com/llms.txt` — summary for AI models
* `yoursite.com/llms-full.txt` — detailed version
* `yoursite.com/.well-known/ucp.json` — structured business profile

= Does this plugin store files on my server? =

No. All content is proxied from the Flowblinq platform and cached in your WordPress database as transients. No files are written to your uploads directory or filesystem.

= How often is the content refreshed? =

Cached content expires every hour. On the next request after expiry, fresh content is fetched from the platform. You can also manually clear the cache from **Settings > Flowblinq GEO > Clear Cache**.

= Does it work with caching plugins? =

Yes. The plugin sets `Cache-Control: public, max-age=3600` headers on proxy responses, which works with page caching plugins and CDNs. After clearing the Flowblinq cache, you may also need to purge your caching plugin or CDN.

= What if my site has "Discourage search engines" enabled? =

The plugin respects that setting. When "Discourage search engines" is enabled in **Settings > Reading**, the plugin does not add AI crawler directives to `robots.txt`.

= What happens when I deactivate or delete the plugin? =

Deactivating removes the rewrite rules (GEO paths stop working). Deleting the plugin also removes all stored options and cached data.

= What is the "Verify My Changes" button? =

After your first audit, click **Verify My Changes** to trigger a second audit. The plugin then shows a before/after comparison of your GEO visibility score.

== Changelog ==

= 1.1.0 =
* Server-side referrer capture — sets `_geo_ref` first-party cookie on every page load so LinkedIn, Twitter, email, and Slack referrals are correctly attributed in GEO analytics. No configuration required.

= 1.0.0 =
* Proxy architecture — serves GEO files via WordPress rewrite rules
* Schema JSON-LD injection with stale-while-revalidate caching
* AI crawler robots.txt directives (GPTBot, ClaudeBot, PerplexityBot)
* Admin settings page with test connection and cache management
* Security hardening (17 findings resolved across 3 adversarial reviews)

= 0.1.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.0 =
Adds automatic server-side referrer tracking. Update to see LinkedIn, Twitter, and email traffic correctly attributed in your GEO analytics dashboard. No configuration needed.

= 1.0.0 =
Major rewrite. Proxy architecture replaces local file storage. All GEO files are now served dynamically. No migration needed — activate and run an audit.
