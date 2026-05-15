=== Flowblinq AI Boost ===
Contributors: adityanittur
Tags: ai, automation, schema, citation-tracking, attribution
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.3.3
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

77% of brands are invisible to AI. Flowblinq is performance automation for AI-specific web pages. We audit, fix, and maintain for you.

== Description ==

**77% of brands are invisible to AI search platforms.** *Loamly / PRWeb, 2025*

Your WordPress site was built for humans. **Flowblinq is performance automation for AI-specific web pages.** The platform audits what ChatGPT, Claude, Perplexity, and Gemini say about your brand, writes the pages they need, and this plugin serves them on your own domain. The system keeps every asset current as those platforms change what they reward.

**We audit, fix, and maintain for you. Automated after setup.**

* **Audit.** What ChatGPT, Claude, Perplexity, and Gemini say about your brand. What they get right. What they miss. What they get wrong.
* **Fix.** Four AI-specific assets served from your own domain: `llms.txt`, `llms-full.txt`, `.well-known/ucp.json`, and per-page Schema.org JSON-LD.
* **Maintain.** The Flowblinq backend monitors what AI platforms actually index, re-evaluates as signals shift, and rewrites your assets automatically. No scheduled regeneration on your server. No re-runs of the audit.

**Recent client deployments:**

* National hospital network: **142,000 to 992,000 weekly page views**, 6 weeks after Flowblinq deployment. *Flowblinq client data, 2026*
* Indian distributor of a global photography brand: **5,800 to 14,700 weekly page views**, 4 weeks after Flowblinq deployment. *Flowblinq client data, 2026*

Requires a Flowblinq account. Get credentials at [geo.flowblinq.com](https://geo.flowblinq.com/dashboard/settings).

== Installation ==

1. Upload the `flowblinq-ai-boost` folder to `/wp-content/plugins/`.
2. Activate the plugin in **WP Admin > Plugins**.
3. Go to **Settings > Flowblinq AI Boost** and enter your Client ID and Client Secret.
4. **Important:** Your site must use "pretty permalinks" (any structure except "Plain"). Go to **Settings > Permalinks** if you haven't set this up.
5. Go to **Tools > GEO Audit** and click **Run Free Audit** to submit your site.
6. Once the audit completes, your GEO files are served automatically.

== Screenshots ==

1. Settings page — enter your Flowblinq Client ID and Client Secret, test the connection, view your assigned site slug.
2. GEO Audit page — one-click audit submission with live progress bar.
3. Audit Results — visibility scorecard with actionable recommendations.
4. Verify My Changes — before/after comparison after a re-audit.

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

Cached content expires every hour. On the next request after expiry, fresh content is fetched from the platform. You can also manually clear the cache from **Settings > Flowblinq AI Boost > Clear Cache**.

= Does it work with caching plugins? =

Yes. The plugin sets `Cache-Control: public, max-age=3600` headers on proxy responses, which works with page caching plugins and CDNs. After clearing the Flowblinq cache, you may also need to purge your caching plugin or CDN.

= What if my site has "Discourage search engines" enabled? =

The plugin respects that setting. When "Discourage search engines" is enabled in **Settings > Reading**, the plugin does not add AI crawler directives to `robots.txt`.

= What happens when I deactivate or delete the plugin? =

Deactivating removes the rewrite rules (GEO paths stop working). Deleting the plugin also removes all stored options and cached data.

= What is the "Verify My Changes" button? =

After your first audit, click **Verify My Changes** to trigger a second audit. The plugin then shows a before/after comparison of your GEO visibility score.

== External Services ==

This plugin connects to **geo.flowblinq.com** (the Flowblinq AI Boost platform), operated by Flowblinq. The plugin must transmit data to this service to function — it is the source of all served content.

**What is sent, when, and why:**

* When you click **Run Free Audit**, the plugin sends a JSON payload containing your site's URL (e.g. `https://yoursite.com`) to `https://geo.flowblinq.com/api/v1/audit`. This is required to start the AI-visibility audit.
* When you click **Verify My Changes**, the plugin sends an empty JSON payload to `https://geo.flowblinq.com/api/v1/audit/{audit_id}/verify` (referencing the audit you started). This re-runs the audit so before/after results can be compared.
* When a visitor (human or AI crawler) requests `/llms.txt`, `/llms-full.txt`, or `/.well-known/ucp.json` on your site, the plugin fetches the corresponding file from `https://geo.flowblinq.com/api/serve/{your-site-slug}/...` and caches the response for one hour. Only your site slug is sent — no visitor data.
* Authentication uses the OAuth 2.0 client_credentials flow against `https://geo.flowblinq.com/api/oauth/token`. Your Client ID and Client Secret are sent only in this request, and the resulting access token is cached in the WordPress transients table for under one hour.

**No visitor data is sent.** The plugin transmits only your site URL, your site slug, and your API credentials. It does not transmit visitor IP addresses, user agents, browsing history, form submissions, or any other end-user information.

**Service terms and privacy policy:** by using this plugin you agree to the Flowblinq Terms of Service at [https://flowblinq.com/terms](https://flowblinq.com/terms) and Privacy Policy at [https://flowblinq.com/privacy](https://flowblinq.com/privacy).

== Privacy ==

This plugin transmits the following data to **geo.flowblinq.com** (Flowblinq):

* Your site URL (when you start an audit).
* Your assigned site slug (when fetching `/llms.txt` and other GEO files for visitors).
* Your API credentials (Client ID and Client Secret) over HTTPS, only when requesting an access token.

This plugin **does not** collect, store, or transmit:

* Visitor IP addresses, browsers, or session identifiers.
* End-user form submissions or comments.
* Any personally identifiable information about anyone other than the site administrator who configures the plugin.

Stored data on your WordPress site:

* `fqgeo_client_id`, `fqgeo_client_secret`, `fqgeo_site_slug`, `fqgeo_active_audit_id` in the `wp_options` table (settings).
* Cached response payloads in the WordPress transients table (`fqgeo_proxy_*`, `fqgeo_access_token`) — auto-expire within one hour.

When you delete the plugin, all of the above are removed (`uninstall.php` clears them).

== Changelog ==

= 1.3.3 =
* readme.txt rewritten in the Flowblinq brand voice. Plugin functionality unchanged.
* New short description and Tags line position the plugin as performance automation for AI-specific web pages, away from the saturated "llms.txt generator" category.
* Description block follows the brand-book copy structure: urgency stat, direction, position, action. Includes the audit/fix/maintain loop and two anonymized client deployment results.

= 1.3.2 =
* Internal hardening pass before WordPress.org resubmission.
* AJAX handlers now bail out cleanly after a failed permission check (defence-in-depth: wp_send_json_error normally exits via wp_die(), but a custom wp_die handler could otherwise let execution continue past the rejection).
* Schema JSON-LD output is now restricted to singular and front-page contexts. Previously it was emitted on search-result pages, archives, 404 pages, and feeds, where business-profile schema is not appropriate.
* Site slug option (fqgeo_site_slug) now has a register_setting() sanitize callback enforcing the same lowercase-alphanumeric-hyphen format the audit handler validates. Defends against direct option writes via WP-CLI or settings-import plugins.
* OAuth credential sanitize callbacks now use a base64url charset whitelist instead of a deny-list of newlines and angle brackets. Tab characters and other unexpected bytes are rejected.
* Plugin boot moved into a plugins_loaded hook so plugin-conflict tooling can inspect the load order.
* Referrer cookie is no longer trusted blindly when already present — the existing value must be a well-formed cross-site URL or it is overwritten.
* Documented as single-site only — in WordPress multisite installations the OAuth credentials must be entered per network site.
* uninstall.php now removes the _fqgeo_stale_schema_json option used for stale-while-revalidate, plus the _fqgeo_lock_schema_json refresh lock transient.

= 1.3.1 =
* WordPress.org plugin review compliance fixes.
* Renamed all 2-character fq_ prefix identifiers to the 4+ character fqgeo_ prefix per WP.org naming-collision guidelines: every option key (fqgeo_client_id, fqgeo_client_secret, fqgeo_site_slug, fqgeo_active_audit_id), every transient (fqgeo_access_token, fqgeo_proxy_*), the rewrite query variable (fqgeo_serve), and all matching HTML form field IDs/names.
* register_setting() sanitize callbacks for fqgeo_client_id and fqgeo_client_secret now preserve raw OAuth credential bytes — sanitize_text_field() was stripping control characters and collapsing whitespace, which could mangle valid OAuth secrets. New callbacks reject newlines + HTML brackets, enforce a 1024-character cap, and surface settings errors on invalid input. Placeholder-preservation logic for the masked secret display unchanged.
* Contributors line in readme.txt corrected to the registered WordPress.org username.

= 1.3.0 =
* Renamed plugin from "Flowblinq GEO" to "Flowblinq AI Boost" for clarity. New display name in the WordPress admin sidebar and on the plugin listing.
* Plugin slug changed from flowblinq-geo to flowblinq-ai-boost (the folder under wp-content/plugins/).
* All internal identifiers (option keys, transient keys, AJAX action names, version constant) are unchanged. No database migration is required when upgrading from 1.2.x.
* Settings menu item now reads "Flowblinq AI Boost". Re-bookmark Settings > Flowblinq AI Boost if you had a direct link.


= 1.2.1 =
* WordPress.org Plugin Check (PCP) compliance — production code now reports zero errors and zero warnings against PCP severity 4.
* Tested up to: 6.7 → 6.9.
* Removed manual load_plugin_textdomain call (auto-loaded by WordPress core since 4.6 for WordPress.org-hosted plugins).
* error_log calls in the proxy are now wrapped in WP_DEBUG checks (production-safe).
* Added wp_unslash() before sanitize_text_field on POST inputs (correctness improvement).
* Added i18n translators-comments above all \%s placeholder strings.
* Inline phpcs:ignore comments with justifications on the proxy pass-through and nonce-via-helper false positives.


= 1.2.0 =
* Added explicit External Services disclosure and Privacy section to readme (WordPress.org compliance).
* Added screenshot descriptions for the WordPress.org listing.
* GPL v2 Copyright line added to LICENSE.
* Internal: Docker integration test infrastructure for staging (ES-044), .htaccess seed + canonical redirect handling.


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
