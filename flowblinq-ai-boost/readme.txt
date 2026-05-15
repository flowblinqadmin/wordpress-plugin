=== Flowblinq AI Boost ===
Contributors: adityanittur
Tags: ai, automation, schema, citation-tracking, attribution
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.3.5
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

77% of brands are invisible to AI. Flowblinq is performance automation for AI-specific web pages. We audit, fix, and maintain for you.

== Description ==

**77% of brands are invisible to AI search platforms.** *Loamly / PRWeb, 2025*

Your WordPress site was built for humans. **Flowblinq is performance automation for AI-specific web pages.** The platform audits what ChatGPT, Claude, Perplexity, and Gemini say about your brand, writes the pages they need, and hosts them on Flowblinq. The plugin makes them available at your domain via WordPress rewrite rules. The system keeps every asset current as those platforms change what they reward.

**We audit, fix, and maintain for you. Automated after setup.**

* **Audit.** What ChatGPT, Claude, Perplexity, and Gemini say about your brand. What they get right. What they miss. What they get wrong.
* **Fix.** Four AI-specific assets hosted on Flowblinq, served at your domain via WordPress rewrite rules: `llms.txt`, `llms-full.txt`, `.well-known/ucp.json`, and per-page Schema.org JSON-LD.
* **Maintain.** The Flowblinq backend monitors what AI platforms actually index, re-evaluates as signals shift, and rewrites your assets automatically. No scheduled regeneration on your server. No re-runs of the audit.

**Recent client deployments:**

* National hospital network: **142,000 to 992,000 weekly page views**, 6 weeks after Flowblinq deployment. *Flowblinq client data, 2026*
* Indian distributor of a global photography brand: **5,800 to 14,700 weekly page views**, 4 weeks after Flowblinq deployment. *Flowblinq client data, 2026*

Requires a Flowblinq account. Get credentials at [geo.flowblinq.com](https://geo.flowblinq.com/dashboard/settings).

== Installation ==

1. Upload the `flowblinq-ai-boost` folder to `/wp-content/plugins/`.
2. Activate the plugin in **WP Admin > Plugins**.
3. Open **Settings > Flowblinq AI Boost**. Enter your Client ID and Client Secret.
4. Set permalinks to any structure except "Plain" at **Settings > Permalinks**.
5. Open **Tools > GEO Audit**. Click **Run Free Audit**.
6. The Flowblinq backend writes your AI assets. The plugin serves them on your domain.

== Screenshots ==

1. Settings page. Client ID, Client Secret, connection test, site slug.
2. GEO Audit page. Audit submission with live progress.
3. Audit Results. Visibility scorecard and recommendations.
4. Verify My Changes. Before-and-after comparison after a re-audit.

== Frequently Asked Questions ==

= Where do I get my API credentials? =

Sign up at [geo.flowblinq.com](https://geo.flowblinq.com). Open **Settings > API**. Generate a Client ID and Client Secret.

= How long does an audit take? =

2 to 5 minutes for most sites. The plugin polls the backend and shows progress.

= What URLs does the plugin create? =

After your first audit, the plugin serves:

* `yoursite.com/llms.txt`: summary for AI models.
* `yoursite.com/llms-full.txt`: detailed version.
* `yoursite.com/.well-known/ucp.json`: structured business profile.

= Does this plugin store files on my server? =

No. Content is proxied from Flowblinq. The plugin caches responses in the WordPress transients table. No files are written to disk.

= How often is the content refreshed? =

Cached content expires after one hour. The next visitor request fetches fresh content from Flowblinq. Clear the cache manually at **Settings > Flowblinq AI Boost > Clear Cache**.

= Does it work with caching plugins? =

Yes. The plugin sets `Cache-Control: public, max-age=3600` on proxy responses. Page caches and CDNs honour this. When you clear the Flowblinq cache, also purge your page cache or CDN.

= What if my site has "Discourage search engines" enabled? =

The plugin respects that setting. With "Discourage search engines" on, no AI crawler directives are added to `robots.txt`.

= What happens when I deactivate or delete the plugin? =

Deactivating removes the rewrite rules. The GEO paths stop returning content. Deleting the plugin removes all stored options and cached data.

= What is the "Verify My Changes" button? =

After your first audit, click **Verify My Changes** to run a second audit. The plugin shows a before-and-after comparison of your visibility score.

== External Services ==

This plugin connects to **geo.flowblinq.com**, operated by Flowblinq. The service is the source of all served content.

**What is sent, when, and why:**

* You click **Run Free Audit**. The plugin POSTs a JSON payload containing your site URL (e.g. `https://yoursite.com`) to `https://geo.flowblinq.com/api/v1/audit`. This starts the AI-visibility audit.
* You click **Verify My Changes**. The plugin POSTs an empty payload to `https://geo.flowblinq.com/api/v1/audit/{audit_id}/verify`, referencing the audit you started. This re-runs the audit for a before-and-after comparison.
* A visitor (human or AI crawler) requests `/llms.txt`, `/llms-full.txt`, or `/.well-known/ucp.json`. The plugin fetches the file from `https://geo.flowblinq.com/api/serve/{your-site-slug}/...` and caches the response for one hour. Only your site slug is sent. No visitor data.
* Authentication uses OAuth 2.0 client_credentials against `https://geo.flowblinq.com/api/oauth/token`. The Client ID and Client Secret are sent only in this request. The access token is cached in the WordPress transients table for under one hour.

**No visitor data is sent.** The plugin transmits your site URL, your site slug, and your API credentials. It does not transmit visitor IP addresses, user agents, browsing history, form submissions, or any other end-user information.

**Service terms and privacy policy.** By using this plugin you agree to the Flowblinq Terms of Service at [https://flowblinq.com/terms](https://flowblinq.com/terms) and Privacy Policy at [https://flowblinq.com/privacy](https://flowblinq.com/privacy).

== Privacy ==

This plugin transmits the following data to **geo.flowblinq.com**, operated by Flowblinq:

* Your site URL when you start an audit.
* Your site slug when fetching `/llms.txt` and other GEO files for visitors.
* Your API credentials (Client ID and Client Secret) over HTTPS, only when requesting an access token.

This plugin does not collect, store, or transmit:

* Visitor IP addresses, browsers, or session identifiers.
* End-user form submissions or comments.
* Personally identifiable information about anyone other than the site administrator who configures the plugin.

Stored data on your WordPress site:

* `fqgeo_client_id`, `fqgeo_client_secret`, `fqgeo_site_slug`, `fqgeo_active_audit_id` in the `wp_options` table.
* Cached response payloads in the WordPress transients table (`fqgeo_proxy_*`, `fqgeo_access_token`). These expire within one hour.

When you delete the plugin, `uninstall.php` removes all of the above.

== Changelog ==

= 1.3.5 =
* WordPress.org Plugin Check fix. Renamed three plugin classes to use the same prefix as the rest of the plugin namespace: Flowblinq_Proxy to Fqgeo_Proxy, Flowblinq_API_Client to Fqgeo_API_Client, Flowblinq_Admin_Page to Fqgeo_Admin_Page. Behaviour unchanged.

= 1.3.4 =
* readme.txt Installation, Screenshots, FAQ, External Services, and Privacy sections rewritten in the Flowblinq voice. Plugin functionality unchanged.
* Description block corrected: Flowblinq hosts the AI assets. The plugin serves them at your domain via WordPress rewrite rules. Previous wording implied the assets were hosted on the customer site.
* Em-dashes in prose replaced with periods, colons, or commas.
* AI-marker phrasing stripped (Typically, actionable, automatically, etc.).
* Shorter sentences and direct imperatives in operational instructions.

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
