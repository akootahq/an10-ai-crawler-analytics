=== Agent Number 10 – AI Crawler Analytics: Verify GPTBot, ClaudeBot & Track AI Visits ===
Contributors: agentnumber10
Tags: ai crawler, robots.txt, chatgpt, analytics, aeo
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See which AI bots really crawl your site. GPTBot & ClaudeBot verified against published IPs. Self-hosted, open source, no account, stores no IPs.

== Description ==

https://www.youtube.com/watch?v=IleQzrTKfOc

**Half the "AI crawlers" in your logs may be fake.** Most AI crawler logs trust the user agent. Anyone can send `User-agent: GPTBot`. This plugin checks every claimed AI crawler visit against the IP addresses its operator publishes, and labels it **Verified**, **Spoofed** or **Unverified**. Only verified visits are ever counted as crawler visits. A visit is called Spoofed only when there is no doubt: if it came through a proxy whose real visitor address the plugin cannot see, or the operator's list could not be refreshed for a week, it is Unverified instead, so a real crawler is never accused.

**Open source, self-hosted, no account.** No external service, no API token, no paywall — your traffic data never leaves your server, unlike cloud tools that pipe your logs to their SaaS.

Verification is off until you turn it on, because it means downloading those published lists from each operator. One click on the plugin's screen starts it, and you can turn it off again in Settings.

AN10 is short for Agent Number 10 (agentnumber10.com), the team that makes this plugin.

**What it shows you, with no account and no setup:**

* **Can AI reach you.** For each AI crawler (ChatGPT, Claude, Perplexity, Google, Bing and the training crawlers), whether your robots.txt lets it in, and the exact line that decides.
* **What AI actually did.** Verified fetches per crawler, the pages they fetched, and when. Googlebot and Bingbot are listed too, but never added to the AI totals: most of what they fetch is ordinary search indexing.
* **The pages no AI crawler has ever fetched.** Often the most useful list here.
* **Visits from AI answers.** Arrivals from ChatGPT, Perplexity, Gemini, Copilot, Claude and others, counted per page. Counted in the browser, so cached pages are included; a reload or a step back is not counted again. At most 2,000 are recorded a day, so a script posting fake visits cannot fill your database. A visit from Google AI Overviews looks like any other Google search visit, so it cannot be told apart.
* **Facts about every page.** Structured data found, heading structure, words before the first h2, question-shaped headings. Counts, never a score.
* **What happened after a fix.** Which verified crawlers fetched the pages you changed, and AI referrals to those pages before and after.

**Free fixes, always previewed, always undoable:**

* Add JSON-LD built only from what WordPress already knows about the page. Nothing guessed.
* Allow AI search crawlers that robots.txt is blocking (training crawlers are left alone).
* Generate llms.txt. No major AI engine has confirmed it reads one, so it is offered as a convenience, not a ranking factor.
* Propose a heading structure without skipped levels, for you to review. Never applied without you.

**Honest about what it cannot see.** When a page cache serves a request, WordPress never runs, so no plugin can log that crawler visit. This plugin detects common caches and says next to every count that the numbers are a floor.

**Works with your SEO plugin.** Yoast SEO, Rank Math, SEOPress and All in One SEO keep titles, meta descriptions, canonicals, Open Graph and sitemaps. This plugin never writes them, and adds structured data only where none is output.

**Whether AI engines cite you** needs the engines to be asked a question, which this plugin never does. The free checker at agentnumber10.com does that, and the plugin mentions it only once it has real crawler data to show you.

== Privacy ==

Unless you click a link to agentnumber10.com, nothing about your site, its content or its visitors leaves your server. Everything is worked out on your site and stored in your own database. The plugin stores, per AI crawler request: the time, the crawler's name, whether its address matched the operator's published list, the page and the HTTP status. Per visitor from an AI answer: the time, the engine and the page. It never stores an IP address, a full user agent, a query string, a cookie or anything that identifies a person.

The admin screens load their fonts and images from the plugin's own folder. Nothing is requested from a font service or any other site when you open them.

== External services ==

**Crawler IP lists.** Off until you turn on crawler verification on the plugin's screen; activating the plugin downloads nothing. Once you turn it on, a scheduled task downloads each operator's published IP range list right away and then once a day, never during a page request. Each download names only the WordPress and plugin versions in its user agent. Your site's address, its content and anything about its visitors are never sent. Turning verification off in Settings stops the downloads and deletes the stored lists.

* OpenAI: https://openai.com/gptbot.json, https://openai.com/searchbot.json, https://openai.com/chatgpt-user.json ([terms](https://openai.com/policies/terms-of-use), [privacy](https://openai.com/policies/privacy-policy))
* Anthropic: https://claude.com/crawling/bots.json ([privacy](https://www.anthropic.com/legal/privacy))
* RIPE NCC (RIPEstat): https://stat.ripe.net/data/announced-prefixes/data.json?resource=AS399358&sourceapp=an10-ai-crawler-analytics, the address ranges Anthropic's own network announces, because Anthropic's list does not include all of them ([terms](https://www.ripe.net/about-us/legal/ripestat-service-terms-and-conditions/), [privacy](https://www.ripe.net/about-us/legal/ripe-ncc-privacy-statement/))
* Perplexity: https://www.perplexity.ai/perplexitybot.json, https://www.perplexity.ai/perplexity-user.json ([privacy](https://www.perplexity.ai/hub/legal/privacy-policy))
* Google: https://developers.google.com/static/crawling/ipranges/common-crawlers.json ([privacy](https://policies.google.com/privacy))
* Microsoft: https://www.bing.com/toolbox/bingbot.json ([privacy](https://privacy.microsoft.com/privacystatement))
* Common Crawl: https://index.commoncrawl.org/ccbot.json ([terms](https://commoncrawl.org/terms-of-use))
* Cloudflare: https://api.cloudflare.com/client/v4/ips, used only to read the real visitor address on sites behind Cloudflare ([privacy](https://www.cloudflare.com/privacypolicy/))

**Free AI visibility checker (agentnumber10.com).** Once the plugin has recorded a verified AI crawler visit, the Overview screen shows a "Check your AI visibility" button. Only if you click it, your browser opens https://agentnumber10.com/ai-visibility-checker in a new tab with your site's domain filled in, so the check can start. If you never click it, nothing is sent. ([privacy](https://agentnumber10.com/privacy), [terms](https://agentnumber10.com/terms))

== Frequently Asked Questions ==

= Why does the crawler panel say nothing yet? =

It only counts a visit after checking the address against the operator's list, so crawler verification has to be on first. After that, a new install takes time to see crawlers. The panel says so rather than showing unverified numbers.

= My site is behind Cloudflare. Does verification work? =

Yes. With verification on, Cloudflare is detected from its published ranges, so leave "Your proxy sets" in Settings on None. For any other proxy, choose the header it sets, and if the proxy reaches your server from a public address, list its addresses under "Your proxy's addresses". The header is read only from those addresses, private ones and Cloudflare, because anyone can send it straight to your server. Until then its visits are labelled Unverified, never Spoofed.

Cloudflare can also block AI crawlers, or add its own rules to robots.txt, before a request reaches WordPress. The plugin cannot see that, and says so on sites behind Cloudflare.

= Does it slow my site down? =

Human visits cost one string comparison of the user agent. A claimed AI crawler visit adds one database insert after the page is sent.

= Does it block crawlers? =

No. It observes. Blocking is a different job.

= Is it really open source and free? =

Yes. GPLv2, no paid tier, no account, no external SaaS. The code is yours to read, run and modify.

== Credits ==

The admin screens use the Inter and JetBrains Mono typefaces, bundled under the SIL Open Font License 1.1. The licence and copyright notices are in assets/fonts/OFL.txt.

== Screenshots ==

1. See who's really crawling you — verified AI crawler fetches, pages fetched and visits from AI answers, by day.
2. Catch spoofed bots — each AI crawler checked against the addresses its operator publishes, and whether your robots.txt lets it in.
3. Find the pages AI never read — pages no verified AI crawler has fetched, and the facts behind each page's findings.
4. Fix it on the spot — a heading proposal to review. Only the heading levels change, and nothing is applied without you.

== Changelog ==

= 0.2.1 =
* Listing: added a short demo video to the plugin page and refreshed the screenshots. No code changes.

= 0.2.0 =
* New: the Overview now leads with the number of spoofed crawler visits, the ones using a real crawler's name from an address its operator does not publish, so the one figure only this plugin can show is the first thing you see.
* New: export every recorded crawler visit as a CSV, straight from your own database, for your records or your own analysis. Nothing leaves your server.
* Improvement: a new install now shows what to expect before any crawler has arrived, with a clear control to turn verification on, instead of an empty screen.

= 0.1.2 =
* Fix: a real Claude visit could be labelled Spoofed. Anthropic's published list leaves out some of its own addresses, so the ranges Anthropic's own network announces now count too, read daily from RIPE NCC's public routing data while verification is on.
* Fix: with a proxy header chosen in Settings, a request sent straight to your server, past the proxy, could carry a crawler's address in that header and be counted as verified. The header is now read only from your proxy: private addresses, Cloudflare, or the addresses you list in the new "Your proxy's addresses" setting.

= 0.1.1 =
* Fix: with a proxy header chosen in Settings, a fake crawler could write a crawler's address into X-Forwarded-For and be counted as verified. The header is now read from the right, so only the address your proxy added counts.
* Fix: on sites behind Cloudflare, Cloudflare's own header is used even when another header is chosen in Settings, and Settings says to leave it on None.

= 0.1.0 =
* First release.
