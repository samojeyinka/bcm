# BCM Developer Test: Answers

Code answers live in their folders (`q01-php`, `q04-css`, `q05-html`, `q06-js`, `q09-api`). Written answers are below. Each code question has a short summary here too.

---

## Q1. PHP (`q01-php/search-results.php`, evidence in `q01-php/evidence.txt`)

**Errors and their effects**

| # | Error | Effect |
|---|-------|--------|
| 1 | `WP_Query([...])` has no `new` | Fatal: call to undefined function `WP_Query()` |
| 2 | `'page'` is not a `WP_Query` pagination argument (`paged` is) | Page 2 shows the same ten posts as page 1 |
| 3 | `$_GET['s']` read without `isset`, passed unsanitised | PHP 8 warning "Undefined array key" when `s` is missing; `esc_html(null)` passes `null`, deprecated since 8.1 |
| 4 | `$page` unvalidated and echoed raw with `<?= $page ?>` | `?page=<script>` is an XSS hole; `?page[]=1` or `abc` breaks the query |
| 5 | `$pages` never defined (should be `$q->max_num_pages`) | Warning, and the heading reads "Page 1 of " |
| 6 | `<h1>` never closed; "Search for" shown even with no search | Broken markup, odd heading |
| 7 | Regex `#[ad]#` is a character class, not the text `[ad]` | Matches every `a` and `d` in the post (I ran it: `a [ad] d` becomes `X [XX] X`). It needs `\[ad\]` |
| 8 | `preg_replace_callback` has no subject argument (`$content`) | Fatal ArgumentCountError |
| 9 | Callback takes no `$m` but uses `$m[1]`; `[ad]` has no capture group anyway | Undefined variable. The group never existed, so there is no number to use |
| 10 | Callback `echo`es instead of `return`ing | The div is printed immediately, before the title, and the post text is wiped |
| 11 | `esc_html(the_title())` | `the_title()` echoes and returns nothing, so the title is printed **unescaped** and `esc_html` gets `null`. Use `get_the_title()` |
| 12 | `get_the_content()` used raw | No `the_content` filters: no paragraphs, shortcodes or embeds |
| 13 | `<p>` wrapped around block HTML | Invalid nesting (`<div>` inside `<p>`); the browser auto-closes the `<p>` |
| 14 | No `wp_reset_postdata()`, no empty-state, opening `<?php` missing at the top | Global `$post` is left changed, blank page on no results |

**Assumptions about the ad placeholders**
- `[ad]` is plain text in the post body, not a registered shortcode.
- The JS ad script finds slots by `.ad` or `data-ad-slot`. I number slots with a running counter across the page (`ad-1`, `ad-2`, ...) so every slot is unique. The original `$m[1]` can't give a number because the marker has none.
- A paragraph containing only `[ad]` is replaced whole so there is no `<p><div>`. An `[ad]` in the middle of a sentence still gets a `div` inside its `<p>`; if that matters, put markers on their own line.
- Replacement runs after `the_content` filters, so `wpautop` can't mangle the div.
- `?page=` is used as asked. On some permalink setups WordPress reserves `page`; if that happens, switch to the built-in `paged` var.

**Checks:** `php tests/run-tests.php` gives 23 passes, covering:
- missing parameters
- page 2 (posts 11-20) and page 3 (5 posts, no Next link)
- bad values (`abc`, `-4`, `0`, array, `99`)
- no search results
- an XSS attempt
- multiple `[ad]` markers
- PHP warnings turned into failures

*Limit:* the tests use small stand-ins for WordPress functions (`tests/wp-stubs.php`), not a real WordPress install. The next step would be dropping the template into a theme on Local/wp-env and repeating the URLs by hand.

---

## Q2. Editor cannot upload images

**Likely causes, in the order I'd check**

1. **Capability or role difference.** The `upload_files` capability may be missing for that user (custom role, a roles plugin, or an edited role).
   *Evidence:* the Media menu is missing or the message says "Sorry, you are not allowed to upload files". `wp user get <id> --field=roles`, then `wp cap list editor`, or Query Monitor's capability checks.
   *Fix:* restore `upload_files` on the role, not as a one-off for the user, and note what removed it.
2. **The file, not the person.** Wrong type (HEIC, SVG), too large (`upload_max_filesize`, `post_max_size`, nginx `client_max_body_size`), or a filename or `upload_mimes` restriction.
   *Evidence:* swap the test. The editor uploads the colleague's file and the colleague uploads the editor's. If the failure follows the file, it is the file. The error text names the limit.
   *Fix:* convert or compress, or raise the server limit with a reason.
3. **Security layer blocking only this user or network.** A WAF/Cloudflare rule, a security plugin, or an IP/country rule.
   *Evidence:* the browser's Network tab shows 403/406 on `async-upload.php` or `/wp-json/wp/v2/media`; WAF or plugin logs show the block.
   *Fix:* allow the specific rule or IP and re-test.
4. **Session or browser.** Stale nonce from a cached admin page (`rest_cookie_invalid_nonce`), extensions, or an expired login.
   *Evidence:* works in a private window or another browser.
   *Fix:* log out and in, exclude `wp-admin` from page cache, clear the nonce-bearing cache.
5. **Server-side processing.** GD/Imagick memory exhaustion ("HTTP error" after a long wait) or an uploads folder that isn't writable.
   *Evidence:* PHP and web-server error logs, disk space. Less likely, because the colleague can upload, unless it only breaks for large images.

**Order and why:** first get the exact message, a screenshot and the Network response (cheap, and it often settles the question). Then compare roles and capabilities. Then the file swap, which separates person from file. Then private window or other network, then logs, then plugins disabled on staging. Change one thing at a time and write down what was ruled out.

---

## Q3. Slow article page on mobile

| Improvement | Measure first |
|---|---|
| **A. Images:** responsive `srcset`/`sizes`, WebP/AVIF, explicit width/height, `loading="lazy"` below the fold, and `fetchpriority="high"` on the lead image only | Field LCP and CLS (CrUX/PageSpeed), which element is the LCP, image bytes on a throttled mobile run (WebPageTest) |
| **B. Third-party scripts (ads, analytics, widgets):** defer or async, load below-fold ad slots on demand, reserve slot height | Total Blocking Time, long tasks, request waterfall, CLS from ad slots |
| **C. Server and cache:** full-page cache for anonymous readers, CDN, object cache, fewer slow queries | TTFB, cache hit ratio, slowest queries (Query Monitor) |

**Chosen: A (images).**
- *Baseline:* median of at least 5 Lighthouse mobile runs (same throttling) on three representative articles, plus the current field LCP and CLS.
- *Change:* ship on staging, then to a slice of traffic or one section first. Re-run the same tests and compare.
- *Ads:* ad requests still fire and render, slots keep their size, CLS doesn't rise. Compare ad impressions per pageview before and after.
- *Analytics:* page_view counts per session match the previous week, events still fire (DebugView/Realtime), no duplicate hits.
- *Signed-in:* log in as editor, check admin bar, preview, draft view and image editing. Make sure no resized image URLs are cached for the wrong audience.
- *Success:* LCP and image bytes drop with ads, analytics and logins unchanged. If any one fails, revert that part.

---

## Q4. CSS (`q04-css/`)

- **Files:** `index.html` and `style.css`. Each section appears once. No JS.
- **Technique:** CSS Grid, mobile first. Three columns on desktop with `#a {grid-column: span 2}`. On mobile, `order` gives A, D, B, C, G, and `display:none` hides E, F and H.
- **Breakpoint:** `48em` (768px). Below it the single-column layout fits a phone, above it three columns have room.
- **Keyboard and screen readers:**
  - **DOM order:** I wrote the HTML in desktop reading order (A B C D E F G H), because keyboard focus and screen readers follow source order, not visual order.
  - **Mobile mismatch:** on mobile, D is visually second but fourth in the source. That is harmless while the boxes contain only text. If D gets links or buttons, focus would jump down and back, which WCAG 2.4.3 flags. In that case I'd ask design to move D or put it right after A in the source.
  - **Hidden sections:** `display:none` removes E, F and H from the tab order and the accessibility tree, so they can't take focus unseen.
  - **Structure:** sections have `aria-labelledby` headings and there is one `<h1>` (visually hidden).
- **Long heading check:** `long-heading-test.html` has a 90-character unbroken word and a long normal heading. `overflow-wrap:anywhere` plus `min-width:0` and `minmax(0,1fr)` keep them inside the cards. The headless check reported 0px horizontal overflow at 1280px and 375px.
- **Bug found by my own check:** the first version's ID-based `order` rules outranked the desktop reset and put E, F, H first. The visual-order check (`ABCDEFGH` on desktop, `ADBCG` on mobile) caught it.
- **Screenshots:** `screenshot-desktop.png`, `screenshot-mobile.png`, plus `-long-heading` versions.

---

## Q5. HTML (`q05-html/index.html`, `validation.txt`)

**Real HTML5 validity errors** (confirmed by the Nu Html Checker; `original-validation.txt` is its output on the original)
1. No `<!DOCTYPE html>`.
2. `</html>` closes before `<body>` (the validator then reports a stray `body` and stops).
3. `name` attribute on `<abbr>` is not allowed; use `title`.
4. `with=300` on `<img>` is not an attribute (typo for `width`).
5. `height=auto` is not valid: it must be an integer.
6. `<img>` has no `alt`.
7. `<code><b>...</code></b>` is wrongly nested.

**Valid, but improved as style/accessibility**
- Unquoted `href=` and `</a >` are legal HTML, so they are not errors. I quoted the attribute and tidied the tag anyway.
- Added `type="button"` to the button, and trimmed the spaces inside the link.
- The link text "odio sem nec elit. Sed posuere" doesn't describe its target. I left it, because you asked to keep the paragraphs; I'd change it in a real article.
- `<b>` carries no meaning; `<strong>` would if the emphasis matters.

**HTML-only discoverability/interpretation improvements**
1. `lang="en"`, charset and viewport meta help search engines, translators and screen readers pick the language and render on phones.
2. A descriptive `<title>` and `<meta name="description">` give search results a meaningful headline and snippet.
3. `<link rel="canonical">` and Open Graph tags tell search engines the preferred URL and give link previews a title, description and image.
4. Meaningful `alt` text plus `width`/`height` let search and assistive tech understand the image and avoid layout shift.
5. `<main>` and `<article>` mark the primary content for crawlers and screen-reader navigation.

*Assumption:* canonical and `og:image` use the placeholder domain `www.example.com`. They should be replaced with the real article URL.

**Validation:** `java -jar vnu.jar index.html` reports 0 errors and 0 warnings (the original reports 2 errors + 1 fatal + 1 warning).

---

## Q6. JavaScript (`q06-js/`, evidence in `q06-js/evidence.txt`)

- **Click:** appends a shuffled (Fisher-Yates) copy of the three original paragraphs after the last paragraph. Copies come from a snapshot taken on load, so state never leaks between batches.
- **HRs:** removed and rebuilt after every change. One goes after each even-numbered paragraph when another paragraph follows (P1 P2 HR P3 P4 HR P5).
- **Toggle button:** created by `script.js`, placed after "Hit me". It sets `hidden` on paragraphs containing `<img>` and keeps `aria-pressed` and its label in sync.
- **New paragraphs while hidden:** visibility is applied in one function that runs after every click, so new image paragraphs inherit the current state.
- **Hidden paragraphs still count:** they stay in the DOM and are counted for HR placement.
- **Checks** (`node test.js` in jsdom, clicking the real buttons): 19 passes.
  - Initial sequence is `P,P,HR,P`.
  - 30 repeated clicks keep the pattern correct all the way to 93 paragraphs.
  - Each batch contains all three originals exactly once, and N of the 6 possible orders appeared (the test prints N).
  - Toggle before adding: 31 image paragraphs hidden, none removed, separators unchanged.
  - Add while hidden: new image paragraphs are hidden and HRs still correct.
  - Toggle back, then add while visible: everything visible.
- **Known visual detail:** HRs next to hidden paragraphs remain visible, as the brief says to count hidden paragraphs.

---

## Q7. Stale article after a release

**Assumptions (not facts):** there is a CDN or page cache in front of WordPress; I can view headers and purge; the release shipped theme code only. I would confirm each.

**First three checks**
1. **What do affected readers actually get?** Fetch the URL as they would and compare with a cache bypass (`curl -sI` vs. `?nocache=<random>`). Read `Age`, `Cache-Control`, `ETag`/`Last-Modified`, `X-Cache`/`CF-Cache-Status`, and any version stamp in the source. Ask: which device, region, logged in or not, which URL (AMP, `?utm`, `www` or not)?
2. **Is the correction really saved at the origin?** Check the post's published revision and `post_modified` in WP admin, and request the origin directly. This separates a cache or deploy problem from "the edit was never published" (draft revision, scheduled, wrong post).
3. **What's live on every server?** Compare the release SHA or asset version on all nodes, and check that deploy purged the cache and reset OPcache. A partial rollout produces old and new responses at random.

**Telling the cause apart**

| Observation | Points to |
|---|---|
| Origin has new content, edge returns old with a high `Age` | Caching (missing purge) |
| Different responses by node, or only some nodes show the new release | Deploy/code (partial rollout) |
| Origin and DB hold the old text | Content problem |
| New content everywhere, but old after a purge or only for certain cookies/devices | Cache key or `Vary` problem, or code reading a stale transient/object cache |

**Roll back when:** the release is the likely cause, the impact is real (wrong or harmful information not yet corrected, broken pages or ads), and there is no quick safe fix. **Don't** roll back for a pure cache problem (a purge fixes it, and a new release wouldn't), and don't ship "another release immediately" to please editors, since that adds risk without evidence.

**Verify recovery:**
- Same URL from several regions and devices, with and without cookies: new content, `Age` reset, version stamp updated.
- Error logs, ad calls and analytics are normal.
- Watch for 30-60 minutes. Then ask the editor to confirm.

**Update to the editor (send only after check 2):**
"Your correction is saved in WordPress, and we're checking whether some readers are seeing an old cached copy or an issue from the recent release. We'll hold off on a new release until we know which, since it wouldn't clear cached copies, and I'll update you by [time] with either the fix or the next step."

---

## Q8. Initiative and judgment

**Ask or observe first:** sit with one editor through one real publish (or get a screen recording). Count the tools, the copy-pastes and where mistakes happened (wrong link, old title, wrong image or crop). Ask which errors keep recurring and what must stay manual.

**Smallest useful improvement (about one day):** a read-only **"Publishing kit" box on the post edit screen** showing the final title, permalink and featured-image URL (with its dimensions), each with a copy button, plus "Copy all". It adds a few dozen lines of PHP and JS and doesn't touch the publishing flow.

**Simpler alternative, little or no new code:** a shared Google Sheet that pulls title, link and image from the site's RSS feed or `wp-json/wp/v2/posts?_embed` with `IMPORTFEED`/`IMPORTDATA`, one row per article, ready to copy from. No deploy, and it can be dropped anytime.

**Why the box first:** the data is always the live published values, so nothing goes stale between the CMS and the tools; it is hard to get wrong; it can be removed cleanly. If the observation shows editors mostly work from a spreadsheet already, I'd start with the sheet.

**Stays under editorial control:** titles, images, wording, UTM choices and when to publish. The tool only displays and copies; it never posts anywhere.

**Test with one editor:** run 5 real articles. Time the old way against the new way, count errors, and watch where they hesitate. Fix what they stumble on before showing a second person.

**Success:**
- Baseline minutes per article measured first, then a clear drop.
- Zero copy-paste errors over two weeks.
- The editor keeps using it unprompted.
- No new support requests. If nobody uses it after two weeks, remove it.

[FILL IN: your own ≤100-word example — job/study/project, what you built, outcome, lesson]

---

## Q9. APIs and data reliability (`q09-api/subscribe.php`, `test.php`)

**The function** `subscribe($email, &$store, $api, $max, $sleep)` (PHP):
1. **Invalid email:** trimmed and lowercased, then `filter_var`. Invalid addresses are never sent, stored or retried. If the service itself answers 400/422, the record is marked `rejected` and not retried.
2. **Store before calling:** a `pending` record keyed by `sha256(email)` is written first, so a crash mid-call can't lose the signup. That same hash is the **idempotency key** sent on every attempt.
3. **Timeout:** treated as *unknown outcome*, not failure. Retry with the same key after exponential backoff with jitter, so a request the service already accepted is not created twice.
4. **Rate limit (429):** wait the `Retry-After` value, then retry. A **409 "already exists"** counts as success.
5. **Out of attempts (timeout/429/5xx):** the status stays `pending` and returns `queued`, so a cron/queue worker can retry later. The record is never dropped.

**Assumption:** the service supports an `Idempotency-Key` header. If it does not, before every retry I would first look the subscriber up by email (`GET`) and only `POST` if absent, and enforce a unique index on the email hash locally.

**Store:** normalised email (needed to retry), idempotency key, status, attempt count, last-attempt time and next-retry time. Encrypt or restrict the table, and delete or anonymise per policy.
**Log:** key prefix or hash, status, HTTP code, attempt number, duration.
**Avoid logging:** raw email addresses, request/response bodies, API keys and `Authorization` headers.
**In production:** set a real request timeout (e.g. `wp_remote_post(..., ['timeout' => 5])`) and make the status update and retry queue transactional.

**Tests** (`php test.php`, 13 passes), including the one you asked for: **retry after the service has already accepted the request.** The mock service accepts the signup, then throws a timeout. The retry gets a 200 and the service ends with exactly one record. A negative control (service ignoring the key) produces two, which shows why the key matters. The other tests cover invalid emails, 429 with `Retry-After`, a persistent 503 left pending and later completed, 422, and a double submit with different capitalisation.

---

## Q10. Security and code review

**Review of the claim:** "only editors see the button" is not security. Hiding a button changes what the interface shows, not what the server accepts. Anyone can send the request directly (curl, devtools), or trick a logged-in editor's browser into sending it (CSRF). I would not approve it as written.

**Required server-side checks, in order (pseudocode)**
```
register_rest_route( 'bcm/v1', '/post-status', [
  'methods'             => 'POST',
  'permission_callback' => function ( $req ) {
      // 1. authenticated?              (REST cookie auth also verifies the X-WP-Nonce => CSRF)
      if ( ! is_user_logged_in() ) return 401;
      // 2. coarse capability
      if ( ! current_user_can( 'edit_posts' ) ) return 403;
      return true;
  },
  'callback' => function ( $req ) {
      // 3. validate input types and values
      $id     = absint( $req['id'] );                 // positive integer
      $status = sanitize_key( $req['status'] );
      if ( ! $id || ! in_array( $status, [ 'draft', 'pending', 'publish', 'future', 'private' ], true ) ) return 400;

      // 4. the object exists and is the expected type
      $post = get_post( $id );
      if ( ! $post || ! in_array( $post->post_type, [ 'post' ], true ) ) return 404;

      // 5. capability on THIS object (not just the role)
      if ( ! current_user_can( 'edit_post', $id ) ) return 403;
      if ( in_array( $status, [ 'publish', 'future' ], true ) && ! current_user_can( 'publish_post', $id ) ) return 403;

      // 6. business rules (allowed transitions, 'future' needs a date)
      if ( ! transition_allowed( $post->post_status, $status ) ) return 409;

      // 7. update only the field intended, then check the result
      $r = wp_update_post( [ 'ID' => $id, 'post_status' => $status ], true );
      if ( is_wp_error( $r ) ) return 500;

      // 8. audit: user id, post id, old to new status (no content, no tokens)
      log_status_change( get_current_user_id(), $id, $post->post_status, $status );
      return [ 'id' => $id, 'status' => $status ];
  },
] );
```
Authentication and CSRF come first and are cheap. Authorisation on the specific object comes before any business logic or database write, and nothing is written until every check has passed.

**Two tests that must be rejected**
1. **A contributor (or a different author) calls the endpoint directly** with a valid login and nonce and `status=publish` on someone else's post. Expect **403**, the status unchanged, and the attempt logged. This proves the check is the server's, not the button's.
2. **A request with no or an invalid nonce** (a forged cross-site POST, or a stale page), made as a logged-in editor. Expect **401/403** and nothing changed.
(Extras: `status=trash;DROP`/`inherit`, a page or attachment ID, a non-existent ID, and `id[]=1`. Each should return 400/404.)

**How I'd raise it with the developer:** privately and specifically. Show a 3-line curl reproduction as a low-privilege user rather than arguing in the abstract. Credit the right instinct (limiting the UI) and then explain that it is a convenience, not a control. Offer the checklist above and offer to pair on the fix. Keep it about the code, not the person.

**Must be fixed before release:**
- a real `permission_callback` (never `__return_true`)
- nonce/CSRF protection
- input validation with an allow-list of statuses
- object-level capability checks (`edit_post`, `publish_post`)
- post type check
- the tests above automated
- audit logging
*Can follow later:* rate limiting, finer-grained transition rules, UI polish.
