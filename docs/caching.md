# Running the theme behind a CDN or a caching proxy

This guide is for administrators who put a Moodle 5.3 site using `theme_dimensions` behind a CDN (Google
Cloud CDN, Cloudflare or another) or a caching reverse proxy (nginx). It says, for every address the theme
serves or relies on, what a shared cache may keep, for how long and under which key, and what it must never
keep. It then gives example configurations for Cloud CDN, Cloudflare and nginx, and a checklist of `curl`
commands to confirm the result.

Two goals pull in the same direction here: scalability (anonymous traffic answered at the edge, without PHP)
and data privacy (no visitor ever receives a response built for somebody else). Both are met by one approach:
let the origin's headers decide, and cache only a short allowlist of address families whose answers are the
same for everybody.

The guide assumes the CDN or proxy fronts the site's whole hostname (`$CFG->wwwroot`). Moodle sends no CORS
headers on fonts and ES modules, so serving those from a separate assets hostname would need more work than
this guide covers.

Every statement about a vendor's behaviour links to the vendor's documentation, as read on 2026-11-21.
Vendors change defaults; re-check a link before relying on a number. Statements about Moodle and the theme
are from the code (file names given) or were measured on a development site, and say which.

## Principles

1. **Respect the origin's headers; never "cache everything".** Moodle marks every page `private` or
   `no-store` and every shareable asset `public` with a lifetime. A rule that ignores those headers turns a
   correct site into one that hands visitors each other's pages.
2. **Never cache a response that carries `Set-Cookie`.** In Moodle a `Set-Cookie` is a session id. A cached
   copy that replays it gives every later visitor the same session. Never strip `Set-Cookie` to make a
   response cacheable, except on an address whose body is the same for everybody by construction (a theme
   setting's image under a revision); the section on `pluginfile.php` below names the one case.
3. **Pages bypass the cache when the request carries the session cookie.** The cookie is `MoodleSession`
   followed by `$CFG->sessioncookie` (empty by default). This is defence in depth: pages are already
   `private`. Do not apply such a bypass to the asset and public calendar addresses: anonymous visitors carry
   the cookie too (see "Anonymous sessions" below), so the bypass would switch caching off for everybody.
4. **Versioned addresses mean no purge.** The theme's stylesheet, JavaScript, images, fonts and strings are
   served under a revision in the address (`themerev`, `jsrev`, `langrev` and so on); *Purge all caches* in
   Moodle moves every page to new addresses. The public calendar works the same way with a token per half.
   A CDN purge is never part of a deployment.
5. **Negative caching off, or short.** The theme's refusals are sent `no-store`, but a CDN that caches 404 or
   302 answers by its own policy would keep a "not yet" answer after it stopped being true.
6. **The query string is part of the cache key.** Several Moodle addresses carry their parameters in the
   query string (`yui_combo.php?...`, `service-nologin.php?...&cachekey=`). A key without the query string
   serves one answer for all of them.
7. **Do not rely on `Vary: Cookie`.** Moodle does not send it, and a cache keyed on the whole cookie header
   would hold one copy per visitor. Privacy comes from `private`/`no-store` and from the allowlist.
8. **Tokens in addresses are credentials.** The iCal feed, `tokenpluginfile.php` and `/webservice/` carry a
   user's token in the address. Never cache them, and keep their query strings out of CDN logs where your
   CDN lets you choose.

## What each address allows

"Shared cache" means a CDN or a reverse proxy. Lifetimes are what the origin sends today.

| Address | Shared cache may keep it? | For how long | Key | Source |
|---|---|---|---|---|
| `/theme/styles.php/<theme>/<rev>_<subrev>/all` | Yes | 90 days, `immutable` | Full URL | core `lib/csslib.php` (measured) |
| `/theme/boost_union/flavours/styles.php/.../<flavourid>/all` | Yes | 90 days, `immutable` | Full URL (flavour id in the path) | Boost Union `flavours/styles.php` (measured) |
| `/lib/javascript.php/<rev>/...`, `/lib/requirejs.php/<rev>/...` | Yes | 90 days, `immutable` | Full URL | core `lib/jslib.php` (measured) |
| `/core/esm/<rev>/...` (ES modules, React) | Yes | 1 year, `immutable` | Full URL | core `esm_controller.php` (measured) |
| `/theme/image.php/...`, `/theme/font.php/...` | Yes | 90 days, `immutable` | Full URL | core (measured) |
| `/theme/yui_combo.php?...` | Yes | 360 days, `immutable` | Full URL with query string | core (measured) |
| `/lib/ajax/service-nologin.php?...&cachekey=<n>` (strings) | Yes | 90 days, `immutable` | Full URL with query string | core `lib/ajax/service.php` (measured) |
| `/theme_dimensions/calendar/public/{events,courses}/...` | Yes, by design | `s-maxage` from the theme's settings (events 1 day, courses 5 minutes by default) | Full URL | theme `classes/route/controller/calendar_public.php` (from code) |
| `/pluginfile.php/1/theme_dimensions/<area>/<rev>/<file>` (dark logos, category hero fallback) | Only with care (see below) | 60 days, `public` | Full URL | core `theme_config::setting_file_serve()` (from code) |
| `/pluginfile.php/<ctx>/theme_dimensions/{hero,cover,ogimage,badge,summary}/...` (landing page files) | **No** | browser only: 7 days (`summary` 1 hour), `private` | | theme `classes/local/landing/publicfiles.php` (from code) |
| `/pluginfile.php/<ctx>/theme_dimensions/category.../...` (category branding) | **No** | browser only: 7 days, `private` | | theme `classes/local/category/branding.php` (from code) |
| Every page: landing `/theme_dimensions/landing/<id>`, guest entry `...?guest=1`, public category `/theme_dimensions/category/<id>`, calendar `/theme_dimensions/calendar`, every logged-in page, the legacy `/theme/dimensions/*.php` scripts | **No** | `private, max-age=0` or `no-store` | | core `lib/weblib.php` (measured on a core page) |
| `/p/<code>` (short address of a landing page) | **No** | `no-store` redirect | | core router (measured for an unknown code) |
| `/lib/ajax/service-nologin.php` without `cachekey`, including the theme's `get_public_category_rows` (POST) | **No** | no freshness information | | core `lib/ajax/service.php` (measured) |
| `/lib/ajax/service.php`, `/webservice/...`, `/login/...`, `tokenpluginfile.php` | **No** | session or token endpoints | | core |
| `/calendar/export_execute.php?userid=...&authtoken=...` (iCal) | **No**, and keep it out of logs | `private, max-age=0` | | theme `classes/local/calendar/ical.php` (from code) |

Why the landing and branding images are `private`: whether a visitor may see them is decided on every request
(the course or category must still be public), and the address carries only the file's content hash. A CDN
that kept them would go on serving a course's images after the course stopped being public. Browsers keep
them for a week, which covers repeat visits. Do not override this.

Why pages are never cached, the anonymous ones included: an anonymous page carries a sesskey tied to the
visitor's session (the guest entry form posts it back), the same address answers 200 to a visitor and a
redirect to an account (the public category page), and the language can come from the session rather than
from the address.

## Things the origin does that a cache must know

**`Set-Cookie` on public `pluginfile.php` answers.** `pluginfile.php` starts a session, so every answer,
the `public` ones included, carries `Set-Cookie: MoodleSession...` (measured on a development site). What a
cache does with that decides whether the theme's setting images are cached and whether a session id leaks:

- Cloud CDN does not cache a response with `Set-Cookie`
  ([Cloud CDN caching overview][gcdn-caching]). No leak; the images are simply not edge-cached.
- Cloudflare, with Origin Cache Control on (always on for Free, Pro and Business), does not cache it and
  passes the cookie through; with an explicit Edge TTL in a cache rule it removes `Set-Cookie` and caches
  ([Cloudflare cache behavior][cf-behavior], [Cloudflare Cache-Control][cf-cachecontrol]).
- nginx does not cache a response with `Set-Cookie` unless told to ignore the header
  ([nginx proxy module][nginx-proxy]). If you do tell it to, also hide the header, or nginx stores the
  cookie with the body and replays it to everyone.

The only addresses where stripping is safe are the theme's setting files in the system context
(`/pluginfile.php/1/theme_dimensions/...`): the same bytes for everybody, a revision in the address. The
Cloudflare and nginx examples below show how; on Cloud CDN, leave them to the browser cache (60 days).

**Anonymous sessions.** Every anonymous request to a script that does not opt out of cookies starts a
session: pages, redirects and `pluginfile.php` hits (measured). A CDN in front does not change that for the
requests it passes on, so size the session store (Redis) for anonymous page views as well as accounts. The
asset addresses in the table send no cookie and start no session, so caching them at the edge, and the
theme's setting images where your CDN allows it, keeps that traffic away from PHP and the session store.

**`Vary: Accept-Language`.** With more than one language pack installed, Moodle adds
`Vary: Accept-Language` to every answer that goes through its full setup, router answers included
(core `lib/setuplib.php`; measured on core's template route). Cloud CDN does not cache a response whose
`Vary` names a header other than a short allowlist, unless that header is part of the cache key
([Cloud CDN caching overview][gcdn-caching]). The theme's public calendar route takes the header off, since
the language is in its address; so on a multi-language site this affects core's `javascript.php` and the
strings service only. The Cloud CDN section shows the workaround. nginx keeps one
copy per `Accept-Language` value ([nginx proxy module][nginx-proxy]). Cloudflare's documentation says a
`Vary` header is acted on through a Vary setting in Cache Rules and that `Vary: *` always bypasses; what it
does with `Vary: Accept-Language` when no such setting is configured is not stated
([Cloudflare Vary][cf-vary]). Since the language is in the address of every cacheable answer above, keeping
one copy per address is correct.

**Caller-chosen revisions.** Core answers any revision up to the current time on `javascript.php`,
`requirejs.php`, the ES module route and the strings service's `cachekey` with a long `immutable` lifetime.
That exposes no personal data, but anyone can create new cacheable addresses. Rate limiting by client at the
CDN is the tool for that, not the cache rules. The theme's own visitor services refuse a `cachekey`.

## The public calendar

With *Public calendar* on, visitors who are not logged in get the theme's calendar page. The page itself is
private and never cached. It draws its items from two addresses, one per half:

```
/theme_dimensions/calendar/public/events/<token>/<lang>/<from>/<to>
/theme_dimensions/calendar/public/courses/<token>/<lang>/<from>/<to>
```

and `/r.php/theme_dimensions/calendar/public/...` while Moodle's router is not configured. From the code
(`classes/route/controller/calendar_public.php`, `classes/local/calendar/publiccache.php`):

- The route is GET only and declared `cookies: false`: no session is read or started and no `Set-Cookie`
  is sent. The answer is the same JSON for whoever asks, a logged-in browser included. The page's script
  fetches it with `credentials: 'omit'`, so the request carries no cookie either.
- A 200 is sent `Cache-Control: public, max-age=<browser>, s-maxage=<half's lifetime>`, where the browser
  lifetime is 60 seconds or the half's lifetime if shorter.
- The **events** half (category events of public categories) lives for *Public calendar: how long a shared
  cache may keep the events* (`theme_dimensions/calendarpubliceventslifetime`), one day by default.
- The **courses** half (public event courses) lives for *... the courses*
  (`theme_dimensions/calendarpubliccourseslifetime`), five minutes by default. It is short on purpose: a
  course published by mistake and taken back must disappear from the internet quickly.
- The token is the half's revision. It changes whenever an event, a course or a category that half shows
  changes (and every hour for courses, every day for events, for writes that no event announces). The page
  reads the current token on every load, so **a page loaded after a change never asks for an old answer**,
  and no CDN purge is needed. Only a tab opened before the change, or the edge's copy under the old address,
  can still show the old answer, for at most the half's lifetime.
- A request naming an old token gets a 302 to the current address, sent `no-store`. A refusal (unknown
  half, language not installed, malformed or non-canonical window, calendar switched off) is an empty 404
  sent `no-store`. A window starting more than 24 months from the current month is answered but sent
  `no-store`, so what a cache can be made to hold stays bounded.

What the CDN needs: a rule that makes `/theme_dimensions/calendar/public/` (and the `/r.php/` spelling)
eligible for caching and respects the origin's headers. Change the lifetimes in the theme's settings, not
in the CDN: a CDN rule that imposes its own lifetime also overrides `no-store`, and would then keep the
302s, the 404s and the far windows the origin refuses to have cached (Cloudflare documents exactly this for
an Edge TTL that ignores `Cache-Control`, [Cloudflare cache responses][cf-responses]).

Keep serving stale content short or off for this path: a stale copy of the courses half is the one thing
the five-minute lifetime is meant to bound.

## Google Cloud CDN

Cloud CDN is configured on the load balancer's backend service. The settings that matter:

- **Cache mode `USE_ORIGIN_HEADERS`.** It caches only responses with valid caching headers and sends the
  rest to the origin every time ([cache modes][gcdn-modes]). The default for a backend created with gcloud or
  the API is `CACHE_ALL_STATIC` ([caching overview][gcdn-caching]), which caches static content types
  without caching headers for the default TTL and caps the `max-age` sent to browsers at the client TTL, one
  hour by default ([TTL settings][gcdn-ttl]): the 90-day assets would reach browsers as one-hour ones.
  Never use `FORCE_CACHE_ALL`: it ignores `private` and `no-store` and Google warns that it may cache
  per-user content ([cache modes][gcdn-modes]).
- In `USE_ORIGIN_HEADERS` mode the default TTL and max TTL do not apply ([TTL settings][gcdn-ttl]); the
  origin's `s-maxage` takes precedence over `max-age` ([caching overview][gcdn-caching]).
- A response with `Set-Cookie`, `private` or `no-store` is not cached; `no-cache` is cached and revalidated;
  only GET requests are cached ([caching overview][gcdn-caching]). So pages, `pluginfile.php` and the iCal
  feed stay uncached without any extra rule.
- **Cache key.** By default a backend service's key is the whole request URI: protocol, host and query
  string ([cache keys][gcdn-keys]). Keep it.
- **Negative caching** is off by default ([caching overview][gcdn-caching]). Keep it off. If it is on, a
  response with its own `Cache-Control` uses that instead of the policy in `CACHE_ALL_STATIC` and
  `USE_ORIGIN_HEADERS` ([negative caching][gcdn-negative]), so the theme's `no-store` refusals are not kept.
- **Serve while stale** defaults to one day, maximum one week, and also serves stale content while the
  origin returns errors; an origin `stale-while-revalidate` takes precedence ([stale content][gcdn-stale]).
  The theme sends none, so the backend setting applies to the public calendar's courses half. Set it to a
  few minutes. The documentation does not say whether this applies in `USE_ORIGIN_HEADERS` mode.
- Cloud CDN adds an `Age` header to responses it serves from cache ([troubleshooting][gcdn-trouble]), and a
  custom response header can carry `{cdn_cache_status}` (hit, miss, revalidated, stale, uncacheable,
  disabled) ([custom headers][glb-headers]).

```sh
gcloud compute backend-services update moodle-web --global \
  --enable-cdn \
  --cache-mode=USE_ORIGIN_HEADERS \
  --cache-key-include-protocol \
  --cache-key-include-host \
  --cache-key-include-query-string \
  --no-negative-caching \
  --serve-while-stale=300 \
  --custom-response-header='X-Cache-Status:{cdn_cache_status}'
```

**Multi-language sites and `Vary: Accept-Language`.** A `Vary` naming a header outside Cloud CDN's allowlist
blocks caching unless the header is in the cache key; `Accept-Language` is not on the list of headers
refused as cache-key headers ([caching overview][gcdn-caching]). The public calendar route sends no `Vary`
(the theme removes core's), so it is cached on any site. Core's `javascript.php` and strings service keep it
on a multi-language site. Two ways out for those:

- Route `/lib/javascript.php/*` and `/lib/ajax/service-nologin.php` through the URL map to a second backend
  service on the same backends, whose cache key adds `--cache-key-include-http-header=Accept-Language`
  ([cache keys][gcdn-keys]). The copies are split by the raw header value, so the hit rate is lower than the
  address alone would allow.
- Or leave them to the browser cache (90 days, `immutable`): every browser asks for them once per revision.

A single-language site sends no `Vary: Accept-Language` and needs neither.

## Cloudflare

How Cloudflare decides, by default:

- It caches by file extension, not by content type, and does not cache HTML or JSON by default. `.php` is
  not a default extension, but `.css`, `.js`, `.woff2`, `.svg`, `.jpg`, `.webp`, `.mp4` and others are
  ([default cache behavior][cf-default]). Moodle addresses such as `/pluginfile.php/.../logo.png` end with
  an image extension and are therefore eligible by default; the origin's `private` or `Set-Cookie` then keeps
  them out.
- It does not cache a response with `Set-Cookie` ([default cache behavior][cf-default]), and `private`
  and `no-store` prevent caching in either Origin Cache Control mode ([cache responses][cf-responses]).
  `no-cache` and `max-age=0` are cached and revalidated while Origin Cache Control is on
  ([cache responses][cf-responses]); Moodle's pages are `private`, so this does not reach them.
- `s-maxage` overrides `max-age` for Cloudflare and implies `proxy-revalidate`, which disables
  `stale-while-revalidate` ([Cache-Control][cf-cachecontrol]).
- The default cache key is the full URL including the query string ([cache keys][cf-keys]).
- Without caching headers, a 200 is kept for 120 minutes and a 404 for 3 minutes by default
  ([default cache behavior][cf-default]). Responses that send no freshness information (the iCal feed's
  "invalid token" answer, `service-nologin.php` without `cachekey`) are `.php` paths and so not eligible by
  default. A cache rule that makes them eligible with "use default Cloudflare caching behavior" would keep
  them; the rules below never do.
- `CF-Cache-Status` reports the decision: `HIT`, `MISS`, `EXPIRED`, `STALE`, `BYPASS` (eligible, but the
  origin's response was not cacheable), `REVALIDATED`, `UPDATING`, `DYNAMIC` (not eligible at all)
  ([cache responses][cf-responses]).

Cache Rules, in the dashboard under Caching. Each rule's settings use the names on
[Cache Rules settings][cf-rules]. The expressions use the Rules language function `starts_with()`
([Rules functions][cf-functions]); if your plan's editor refuses it, build the same conditions with the
expression builder's "starts with" operator.

**Rule 1, versioned assets: eligible, origin decides.**

```
starts_with(http.request.uri.path, "/theme/styles.php/")
or starts_with(http.request.uri.path, "/theme/boost_union/flavours/styles.php/")
or starts_with(http.request.uri.path, "/theme/image.php/")
or starts_with(http.request.uri.path, "/theme/font.php/")
or starts_with(http.request.uri.path, "/theme/yui_combo.php")
or starts_with(http.request.uri.path, "/lib/javascript.php/")
or starts_with(http.request.uri.path, "/lib/requirejs.php/")
or starts_with(http.request.uri.path, "/core/esm/")
or starts_with(http.request.uri.path, "/r.php/core/esm/")
```

- Cache eligibility: *Eligible for cache*.
- Edge TTL: *Use cache-control header if present, bypass cache if not*.
- Browser TTL: *Respect origin*.

**Rule 2, the public calendar: eligible, origin decides.**

```
starts_with(http.request.uri.path, "/theme_dimensions/calendar/public/")
or starts_with(http.request.uri.path, "/r.php/theme_dimensions/calendar/public/")
```

- Cache eligibility: *Eligible for cache*.
- Edge TTL: *Use cache-control header if present, bypass cache if not*. Not *Ignore cache-control header
  and use this TTL*: that also overrides `no-store` ([cache responses][cf-responses]) and would keep the
  302s, 404s and far windows. To change a half's lifetime, change the theme's setting.
- Browser TTL: *Respect origin*, so a browser keeps a half for the 60 seconds the origin asks and not longer.
- Serve stale content while revalidating: off.

**Rule 3 (optional), the theme's setting images: eligible, explicit TTL.**

```
starts_with(http.request.uri.path, "/pluginfile.php/1/theme_dimensions/")
```

- Cache eligibility: *Eligible for cache*; Edge TTL: *Ignore cache-control header and use this TTL*, for
  example 30 days. With an explicit Edge TTL Cloudflare removes `Set-Cookie` and caches
  ([cache behavior][cf-behavior]); that is safe here only because these are system-context theme settings,
  the same bytes for everybody under a revision. `1` is the system context's id on almost every site:
  confirm it in the address of your dark logo. Use this rule only with theme designer mode off (it sends
  revision `-1`, which would then be kept). Never widen it to other contexts: the landing page and branding
  files of courses and categories are `private` for a reason.

**Rule 4, never cached.**

```
starts_with(http.request.uri.path, "/calendar/export_execute.php")
or starts_with(http.request.uri.path, "/webservice/")
or starts_with(http.request.uri.path, "/tokenpluginfile.php")
or starts_with(http.request.uri.path, "/lib/ajax/")
or starts_with(http.request.uri.path, "/login/")
or starts_with(http.request.uri.path, "/admin/")
```

- Cache eligibility: *Bypass cache*. These would not be cached anyway; the rule keeps it that way if somebody
  later adds a broad rule.

**Rule 5 (defence in depth), pages with a session bypass.** Cloudflare's own example bypasses on a cookie with
`http.cookie contains "..."` ([bypass cache on cookie][cf-cookie]); its documentation does not state which
plans may use the `http.cookie` field in Cache Rules.

```
http.cookie contains "MoodleSession"
and not starts_with(http.request.uri.path, "/theme/")
and not starts_with(http.request.uri.path, "/lib/javascript.php/")
and not starts_with(http.request.uri.path, "/lib/requirejs.php/")
and not starts_with(http.request.uri.path, "/core/esm/")
and not starts_with(http.request.uri.path, "/theme_dimensions/calendar/public/")
and not starts_with(http.request.uri.path, "/r.php/")
```

- Cache eligibility: *Bypass cache*. The exclusions keep the rule from switching off rules 1 and 2 for
  visitors, who carry the cookie too.

Zone settings: Browser Cache TTL *Respect Existing Headers*; otherwise Cloudflare raises a lower origin
`max-age` to its own value ([browser TTL][cf-browserttl]), which would stretch the public calendar's 60
seconds. Cache Deception Armor, which refuses to cache when a URL's extension does not match the response's
content type ([cache deception armor][cf-armor]), is a sensible extra guard for a site with slash arguments.

**What "Cache Everything" would break.** A rule that makes every address eligible with an explicit Edge TTL
would, by Cloudflare's documented behaviour, remove `Set-Cookie` and keep pages
([cache behavior][cf-behavior]), and an Edge TTL that ignores `Cache-Control` also overrides `no-store`
([cache responses][cf-responses]). On this site that means:

- the first visitor's sesskey served to every visitor, so the guest entry form (and every form) fails for
  everybody else;
- the public category page's 200 for a visitor served to an account (or the account's redirect served to
  visitors);
- a page in one visitor's session language served to everybody;
- a logged-in page (a name, a dashboard, a grade) served to whoever asks next, if any request reaches it
  without the cookie bypass;
- the landing and branding images of a course or category that stopped being public served for the whole
  TTL;
- the public calendar's refusals and old-token redirects kept, and far windows filling the cache.

## nginx as a caching reverse proxy

nginx honours `X-Accel-Expires`, `Expires` and `Cache-Control` from the origin, does not cache a response
with `no-cache`, `no-store` or `private`, prefers `s-maxage` to `max-age`
(nginx source, `ngx_http_upstream_process_cache_control` in `src/http/ngx_http_upstream.c`), does not cache a
response with `Set-Cookie` or `Vary: *`, and keeps one copy per value of the headers another `Vary` names
([nginx proxy module][nginx-proxy]). The default key is `$scheme$proxy_host$request_uri`, query string
included ([nginx proxy module][nginx-proxy]).

The safest layout turns the cache on only in the allowlisted locations:

```nginx
proxy_cache_path /var/cache/nginx/moodle levels=1:2 keys_zone=moodle:50m
                 max_size=5g inactive=30d use_temp_path=off;

upstream moodle { server 127.0.0.1:8080; }

server {
    # ... listen, server_name, TLS ...

    # Everything else: pages, services, pluginfile.php, iCal. No cache at all.
    location / {
        proxy_pass http://moodle;
    }

    # Versioned assets: the origin's headers decide.
    location ~ ^(/r\.php)?/(theme/(styles|image|font)\.php|theme/boost_union/flavours/styles\.php|lib/(javascript|requirejs)\.php|core/esm)/ {
        proxy_pass http://moodle;
        proxy_cache moodle;
        proxy_cache_lock on;
        proxy_cache_use_stale error timeout updating;
        proxy_cache_background_update on;
        add_header X-Cache-Status $upstream_cache_status always;
    }
    location = /theme/yui_combo.php {
        proxy_pass http://moodle;
        proxy_cache moodle;
        proxy_cache_lock on;
        add_header X-Cache-Status $upstream_cache_status always;
    }

    # The public calendar: the origin's s-maxage decides; no stale copies.
    location ~ ^(/r\.php)?/theme_dimensions/calendar/public/ {
        proxy_pass http://moodle;
        proxy_cache moodle;
        proxy_cache_lock on;
        add_header X-Cache-Status $upstream_cache_status always;
    }

    # Optional: the theme's setting images in the system context. The same bytes for
    # everybody under a revision, so the session cookie is ignored AND hidden: without
    # proxy_hide_header the stored cookie would be replayed to every visitor.
    location ^~ /pluginfile.php/1/theme_dimensions/ {
        proxy_pass http://moodle;
        proxy_cache moodle;
        proxy_ignore_headers Set-Cookie;
        proxy_hide_header Set-Cookie;
        add_header X-Cache-Status $upstream_cache_status always;
    }
}
```

`proxy_cache_lock` lets one request fill a new entry while the others wait ([nginx proxy module][nginx-proxy]),
which matters on the first view after *Purge all caches*, when every asset address is new. No location sets
`proxy_cache_valid`, so nothing is kept without the origin's own headers, and no 404 is kept. Adapt the
paths if Moodle is not at the root of the hostname.

## Moodle behind a proxy

- Set *Logged IP address source* (`getremoteaddrconf`, under *Site administration > Server > HTTP*) to the
  header your CDN or proxy sets, and list the proxies' addresses in `$CFG->reverseproxyignore`, so Moodle logs
  and rate-limits the visitor's address, not the edge's.
- `$CFG->sslproxy = true;` when TLS ends at the CDN or proxy and Moodle is reached over plain HTTP;
  `$CFG->reverseproxy = true;` for advanced load-balancing layouts (both in `config-dist.php`).
- `$CFG->wwwroot` is the public address, as the visitor sees it.

## Checklist: confirming the headers

Use GET, not `curl -I`: `-I` sends HEAD, which a GET-only route such as the public calendar's may answer
differently. Run each command twice; the second answer of a cached address shows the cache.

```sh
SITE=https://moodle.example.org
hdrs() { curl -s -o /dev/null -D - "$1" \
  | grep -iE '^(HTTP/|cache-control|set-cookie|vary|age|cf-cache-status|x-cache-status|location)'; }
```

1. **A page is never cached.** Expect `private` or `no-store`, a `Set-Cookie`, and no `Age`, no
   `cf-cache-status: HIT`, no `X-Cache-Status: HIT`, on both runs.

   ```sh
   hdrs "$SITE/theme_dimensions/landing/<courseid>"
   hdrs "$SITE/theme_dimensions/category/<categoryid>"
   hdrs "$SITE/theme_dimensions/calendar"
   ```

2. **The stylesheet is cached.** Expect `public, max-age=7776000, immutable`, no `Set-Cookie`, and on the
   second run an `Age` (Cloud CDN), `cf-cache-status: HIT` (Cloudflare) or `X-Cache-Status: HIT` (nginx).

   ```sh
   CSS=$(curl -s "$SITE/login/index.php" | grep -o '/theme/styles.php/[^"]*' | head -1)
   hdrs "$SITE$CSS"; hdrs "$SITE$CSS"
   ```

3. **The public calendar halves are cached, cookie-free.** Open the public calendar in a private browser
   window and copy a `/calendar/public/courses/...` address from the network panel (or take the prefix from
   the page source). Expect `public, max-age=60, s-maxage=300` (courses, default) or `s-maxage=86400`
   (events), no `Set-Cookie`, no `Vary` (even on a multi-language site), and a hit on the second run.

   ```sh
   HALF="$SITE/theme_dimensions/calendar/public/courses/<token>/<lang>/<from>/<to>"
   hdrs "$HALF"; hdrs "$HALF"
   ```

4. **An old token is redirected, uncached.** Replace the token with `00000000`. Expect a 302, a `Location`
   with the current token, `Cache-Control: no-store`, and never a hit.

5. **A refusal is uncached.** Replace `courses` with `nothing`. Expect a 404 with `no-store`, never a hit,
   on both runs.

6. **Landing and branding images stay private.** Copy a landing page's hero image address. Expect
   `private, max-age=604800`, and never an `Age` or a hit.

7. **The iCal feed is never cached.** Use a deliberately wrong token. Expect no hit, no `Age`, on both runs.

   ```sh
   hdrs "$SITE/calendar/export_execute.php?userid=0&authtoken=wrong&preset_what=all&preset_time=recentupcoming"
   ```

8. **Theme setting images** (only if you added the optional rule or location). Expect a hit on the second
   run and **no `Set-Cookie`** on either. A `Set-Cookie` together with a hit means the cookie is being
   replayed: remove the rule at once.

## Not verified

- Whether Cloud CDN's serve-while-stale applies in `USE_ORIGIN_HEADERS` mode
  ([stale content][gcdn-stale] does not say).
- What Cloudflare does with `Vary: Accept-Language` when no Vary setting is configured in a cache rule
  ([Cloudflare Vary][cf-vary] does not say).
- Which Cloudflare plans may use `http.cookie` and `starts_with()` in Cache Rules, and which offer Cache
  Deception Armor (the pages cited do not say).
- The public calendar route's headers were read from the code, not measured on a running site; its
  `Vary: Accept-Language` is inferred from core's template route, which goes through the same router setup.

[gcdn-caching]: https://docs.cloud.google.com/cdn/docs/caching
[gcdn-modes]: https://docs.cloud.google.com/cdn/docs/using-cache-modes
[gcdn-ttl]: https://docs.cloud.google.com/cdn/docs/using-ttl-overrides
[gcdn-keys]: https://docs.cloud.google.com/cdn/docs/using-cache-keys
[gcdn-negative]: https://docs.cloud.google.com/cdn/docs/using-negative-caching
[gcdn-stale]: https://docs.cloud.google.com/cdn/docs/serving-stale-content
[gcdn-trouble]: https://docs.cloud.google.com/cdn/docs/troubleshooting-steps
[glb-headers]: https://docs.cloud.google.com/load-balancing/docs/https/custom-headers
[cf-default]: https://developers.cloudflare.com/cache/concepts/default-cache-behavior/
[cf-cachecontrol]: https://developers.cloudflare.com/cache/concepts/cache-control/
[cf-behavior]: https://developers.cloudflare.com/cache/concepts/cache-behavior/
[cf-responses]: https://developers.cloudflare.com/cache/concepts/cache-responses/
[cf-vary]: https://developers.cloudflare.com/cache/concepts/vary/
[cf-rules]: https://developers.cloudflare.com/cache/how-to/cache-rules/settings/
[cf-keys]: https://developers.cloudflare.com/cache/how-to/cache-keys/
[cf-cookie]: https://developers.cloudflare.com/cache/how-to/cache-rules/examples/bypass-cache-on-cookie/
[cf-functions]: https://developers.cloudflare.com/ruleset-engine/rules-language/functions/
[cf-browserttl]: https://developers.cloudflare.com/cache/how-to/edge-browser-cache-ttl/set-browser-ttl/
[cf-armor]: https://developers.cloudflare.com/cache/cache-security/cache-deception-armor/
[nginx-proxy]: https://nginx.org/en/docs/http/ngx_http_proxy_module.html
