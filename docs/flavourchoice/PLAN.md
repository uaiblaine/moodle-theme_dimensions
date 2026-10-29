# A look of one's own: choosing a Boost Union flavour from the user menu

Status: **S1 merged (#51, 2026100532); S2 implemented, 2026-10-29** (2026100534: the Look submenu, the drawer
panel, `flavour.php` with its form, `amd/src/flavourchoice.js` and the `theme_dimensions_choose_flavour` service). S2
took the choice page's form from S3 and changed default D3 (section 9). S3 implemented 2026-10-29 (2026100536): the preferences page link. S4 and S5 are not started. The owner's
decisions are in section 2.
F1 was reversed the same day: the person's choice now wins over the administrator's flavours.
Section 9 lists the defaults this plan picked where nothing was decided; each can be overturned
before its stage starts.

## 1. Goal

Let a logged-in person choose how the site looks, among the Boost Union flavours the administrator
offers. The choice is made from a "Look" submenu in the user menu that behaves like core's language
submenu, and from a page linked on the preferences page. It is remembered across sessions and devices.
An administrator can also show or hide Boost Union smart menus by the look a person chose (stage S5).

**Not in scope:**

- Compiling the flavours' stylesheets ahead of time. Another line of work owns the cold compile
  (section 7). This feature makes that work a **prerequisite for release**, but not for development.
  Done: `docs/flavourcss/PLAN.md`, merged as #52, #57 and #59. Its planner compiles every flavour
  `selectableflavours` offers, beside the category and cohort ones.
- New flavour fields, or any change to how Boost Union compiles a flavour.
- The Moodle App, which does not use the theme's stylesheet.
- Dark mode. Core's colour mode (`theme_boost\colour_mode`, `data-bs-theme`) is applied at runtime and
  combines with any flavour as it does today.

## 2. Decisions (owner, 2026-10-28)

| # | Question | Decision |
|---|---|---|
| F1 | Precedence between the person's choice and the administrator's flavours | **The person's choice wins.** A flavour that applies through a category or a cohort is shown only to people who chose nothing ("Site default"). This reverses the first answer of the same day, under which the administrator's flavours won. |
| F2 | Which flavours can be chosen | **A list in a theme_dimensions setting.** |
| F3 | Visitors who are not logged in, and the guest account | **No choice.** Nothing changes for them. |
| F4 | Where the choice is made | **In the user menu**, with behaviour similar to the language submenu, **and on the preferences page.** |
| F5 | Smart menus by look | **Adopted:** one cohort per offered flavour, kept in step with the choice, so Boost Union's own cohort restriction does the work (section 5, stage S5). |

## 3. How Boost Union decides the flavour (read 2026-10-28, `upgrade-503` 143e7497)

- **One resolver.** `theme_boost_union_get_flavour_which_applies()` (`flavours/flavourslib.php:34`)
  is the only function that decides. All nine places that apply a flavour call it:
  - the stylesheet URL (`lib.php:1348`, reached through `theme_dimensions_alter_css_urls()`);
  - the favicon, logo, compact logo and the `flavour-<id>` body class (`classes/output/core_renderer.php:104, 204, 318, 454`);
  - the navbar colour, the footer popover and the footnote (`layout/includes/navbar.php:46`, `footer.php:168`, `footnote.php:73`);
  - this theme's dark logo (`classes/local/darklogo.php:180`).
- **What it does, in order:**
  1. A function `static` holds the answer for the rest of the request. Nothing outside the function can
     reset it.
  2. The preview page takes the flavour from its `id` parameter.
  3. If the person's `theme_boost_union_flavours_purgesessioncache` preference is set, it purges the
     session cache.
  4. It reads the session cache `theme_boost_union/flavours` (`db/caches.php`), keyed by the page's
     category id (0 outside a category), which holds the whole flavour record or null.
  5. On a miss, it walks every flavour by `sort`: categories (with subcategories), then cohorts. It
     caches the first match, or null.
- **Upstream defect:** `classes/eventobservers.php:79, 107` sets that purge flag when someone joins or
  leaves a cohort that has a flavour, and nothing ever clears it. The smart-menu twin of the flag is
  cleared (`smartmenus/menulib.php:681`). From then on, step 3 purges that person's cache on every
  request and step 5 re-runs: all flavours, then their cohorts, on every page. It would also erase a
  primed choice (section 4.2, step 2).
- **The stylesheet does not depend on the person.** `flavours/styles.php` compiles from the flavour id
  in the URL and caches the result under `<theme>_<flavourid>_ltr`. So the compile cost grows with the
  number of flavours offered, never with the number of people choosing them.

## 4. Design

### 4.1 Priming Boost Union's session cache

The theme writes the chosen flavour into Boost Union's session cache before the first consumer runs.
Boost Union then answers all nine consumers from the cache, and any consumer added upstream later
reads the same cache. The alternative, overriding each consumer, would copy the CSS URL rewrite, four
renderer methods, and Boost Union's three layout includes. The includes can only be replaced by owning
every layout file (`CLAUDE.md`, "The name in the navbar").

**With F1 as decided, the theme copies nothing of Boost Union.** A person with a valid choice gets it
on every page, whatever the administrator's rules say. A person without one is left to Boost Union
entirely. The theme never needs the administrator's answer, so it never needs Boost Union's matching
loop. That loop is the copy the first version of this plan had to carry.

### 4.2 Per request

A new hook callback `theme_dimensions\local\hook\output\before_http_headers::callback` is registered
in `db/hooks.php` at a high priority. Core dispatches it in `core_renderer::header()` (core
`lib/classes/output/core_renderer.php:911`), before it includes the layout (`:942`). No Boost Union
hook resolves a flavour earlier: neither `after_config` nor the head hooks do.

1. **Return unless all of these hold:** the page's theme is `dimensions`, the person is logged in and is
   not the guest, and the page is not Boost Union's `flavours/preview.php`. Hook callbacks of an
   inactive theme still run, which is why the first check is needed.
2. **Work out the effective choice.** It is the `theme_dimensions_flavour` preference, but only while
   that id is in the `selectableflavours` setting **and** the flavour still exists. Decide on the
   recorded fact: a deleted or unlisted flavour reads as 0, "Site default", whatever the stale
   preference says.
3. **Compare with the session marker.** A new session cache, `theme_dimensions/flavourchoice`, holds
   one entry per session: the effective choice the session was last primed with, and that flavour's
   record. Its definition lists Boost Union's five invalidation events, so editing, re-sorting or
   deleting a flavour, or deleting a cohort, purges it together with Boost Union's cache. If the
   effective choice differs from the marker, purge Boost Union's `flavours` session cache, read the
   record (one `get_record`), and write the new marker. That covers a choice made in another session
   and a flavour taken off the list.
4. **Handle Boost Union's purge flag** while the effective choice is not 0. If the flag is set, purge
   Boost Union's session cache and clear the flag. That completes what the flag was meant to do, once
   (section 3), and keeps the resolver's step 3 from erasing what step 5 writes. A drift test pins the
   defect, so the clearing is removed when upstream fixes it.
5. **Prime** while the effective choice is not 0. If Boost Union's cache entry for the page's category
   id is not the marker's record (compare the id), write the record there. While the choice is 0,
   write nothing: Boost Union resolves the administrator's rules as it always has.

**Cost:**
- Steps 1 to 3 are in-memory reads. The preference is already in `$USER->preference`, the setting in
  the config cache, and both session caches live in the session, which is Redis on the stacks
  (`$CFG->session_handler_class = '\core\session\redis'`).
- One `get_record` per session, and again after a flavour edit.
- For a person with a choice, Boost Union's own scan (all flavours, then their cohorts) never runs.
  That is a database saving, not a cost.

### 4.3 Writing the choice

- The preference is `theme_dimensions_flavour`: the flavour id, or 0 for "Site default". It is declared
  in `theme_dimensions_user_preferences()` (`PARAM_INT`, `permissioncallback` =
  `core_user::is_current_user`), beside the focus-mode and showcase preferences.
- **Endpoint `/theme/dimensions/flavour.php`.** It requires login, and refuses visitors and the guest.
  - **GET `?choose=<id>&sesskey=…&returnurl=…`** (from the menu):
    1. `require_sesskey()`.
    2. Validate `<id>` against the setting (0 is always valid).
    3. Write the choice (section 4.7: the preference, and from S5 the cohorts).
    4. Purge this session's Boost Union and `flavourchoice` caches.
    5. `redirect()` to the `PARAM_LOCALURL` return address.

    This is the language submenu's behaviour, a link that reloads the same page. It carries a sesskey
    because this link writes to the database, while `?lang=` writes only the session.
  - **Without `choose`** (from the preferences page): a moodleform with one radio per offered flavour
    plus "Site default", each with the flavour's title and description (`format_string` /
    `format_text`), sent by POST. It returns to `/user/preferences.php` with a notification.

### 4.4 The user menu

- In `core_renderer::render_from_template()`, guarded by the context's shape: `usermenu.submenus`
  beside `usermenu.avatardata`, which only a logged-in account has. This is the same family of guards
  the method already uses (`CLAUDE.md`, "The name in the navbar").
- Add a `submenu-link` item "Look" just before "Language" (before "Log out" when there is no language
  item). Build its submenu like core's language submenu (core
  `lib/classes/navigation/output/primary.php:488-520`).
- The submenu lists "Site default" plus each offered flavour, in `sort` order. The current choice is
  the active item (url `#`, as core marks the current language). Every other item links to the GET
  endpoint above.
- **The theme's mobile drawer** (`classes/local/drawernav.php`) gets a "Look" panel beside its language
  panel (`drawernav::LANGUAGE`, `language()` at `:296`), with the same items.
- Nothing is added when the setting is empty, for visitors and the guest (F3), or on Boost Union's
  preview page.

### 4.5 The preferences page

`theme_dimensions_extend_navigation_user_settings()` in `lib.php` adds a "Look" link to the
`useraccount` node. The link appears only on `/user/preferences.php` and only for the person's own
preferences. This is the `tool_mfa` pattern (core `admin/tool/mfa/lib.php:67`).

### 4.6 The setting

- `theme_dimensions/selectableflavours` is an `admin_setting_configmulticheckbox` on a "Look" section of
  the existing general tab.
- Its choices come from a **callable** (core `admin/classes/setting/setting/configmulticheckbox.php:53`),
  so `settings.php` reads no table at install (fleet memory "settings.php bare $CFG breaks install").
- It defaults to empty, so the feature is off until an administrator chooses flavours.
- Changing it needs no theme cache reset, because no stylesheet depends on it.
- From S5 its updated callback also provisions the cohorts. Like every such callback it
  short-circuits during install (fleet "Security defaults").
- The description tells the administrator two things:
  - A listed flavour is shown on every page to whoever picks it, over the category and cohort rules
    (F1), so it must work outside the category it was designed for.
  - The look cohorts of S5 are not to be used in flavour rules (section 4.7).

### 4.7 Smart menus by look (stage S5)

**Boost Union's smart menus have no extension point.** `smartmenu_helper::verify_access_restrictions()`
(`smartmenus/menulib.php:82`) knows roles, site admin, cohorts, language and dates. The helper is
instantiated directly, and nothing in it dispatches a hook or a callback. Their cache key is per user,
`<menu>_u_<userid>` (`classes/smartmenu.php:469`), so any restriction must depend on the person and not
on the page. That rules out restricting by the flavour *applied* to a page, and leaves the *chosen*
one.

**One cohort per offered flavour carries the choice into Boost Union's cohort restriction:**

- **Provisioning.** The setting's updated callback, and a scheduled reconcile task, ensure one cohort
  per listed flavour:
  - `component = 'theme_dimensions'`, system context;
  - `idnumber = 'theme_dimensions_look_<flavourid>'`;
  - the name from a lang string over the flavour's title, e.g. "Look: Cor".

  The check-then-act runs under a core lock, as the fleet requires of provisioning without a unique
  index.
- **Membership** is written in the same request as the preference (section 4.3, step 3): out of the
  previous look's cohort, into the new one, under a lock keyed by the user, so two tabs choosing at
  once cannot leave a person in two cohorts. Core's `cohort_member_added`/`removed` events reach Boost
  Union's observer, which purges that person's menu cache at once (`purge_cache_session_cohort()`,
  `menulib.php:486`). The next page shows the new menus.
- **The preference is the record; membership is derived from it.** The reconcile task puts membership
  back in step with the effective choice of section 4.2, step 2, and never the reverse.
- **A cohort is never deleted while the feature exists.** When a cohort is deleted, Boost Union removes
  it from every menu rule (`remove_deleted_condition_menu()`, `menulib.php:421`), so a menu restricted
  by that cohort alone opens to everyone. A flavour taken off the list therefore has its cohort
  *emptied* and kept, through an adhoc task above about 500 members (fleet "Mass group-membership
  writes"). On uninstall, the cohorts are released rather than deleted: `component` is cleared and the
  members are kept, so they become ordinary cohorts an administrator can manage (section 9, D7).
- **The cohort lock, as tool_dynamic_cohorts does it, plus the one hole it leaves.**
  - **The lock is the `component` stamp.** tool_dynamic_cohorts locks a cohort linked to a rule by
    writing its own name into `cohort.component` (`cohort_manager::manage_cohort()`). It unlocks with
    `unmanage_cohort()`, which clears the stamp and keeps the cohort, members included unless
    `releasemembers` is on. D7 is the same release.
  - **What the stamp blocks in core** is the interface only:
    - the cohort list drops the edit, delete, show/hide and assign actions
      (`cohort/classes/reportbuilder/local/systemreports/cohorts.php:206-252`);
    - `cohort/edit.php:62` and `cohort/assign.php:48` redirect away.
  - **What it does not block** is core's web services: `core_cohort_delete_cohorts`, `update_cohorts`,
    `add_cohort_members` and `delete_cohort_members` (`cohort/externallib.php`) check
    `moodle/cohort:manage` and never the component. `cohort_delete_cohort()` itself only carries a
    "component delete callback" note at `cohort/lib.php:136`, with no callback behind it. tool_dynamic_cohorts has the same hole. Here it matters more, because a deletion opens
    menus.
  - **The safeguard for the hole is fail closed.** A theme observer on `\core\event\cohort_deleted`
    at a priority above Boost Union's (Boost Union's declares none, so it runs at 0, and observers run
    highest priority first) reads the deleted cohort from the event's record snapshot. If it was a
    look cohort, the observer hides, through Boost Union's own `smartmenu::update_visible(false)`
    (`classes/smartmenu.php:391`) and `smartmenu_item::update_field('visible', false)`
    (`classes/smartmenu_item.php:650`, the call `smartmenus/items.php:111` makes), every menu and item whose `cohorts` rule names
    it, before Boost Union's observer strips the id. A menu that would have opened to everyone stays
    hidden instead. The reconcile task recreates the cohort under a new id and leaves the
    administrator a notice naming the menus to re-point and show again.
  - **Membership written behind the theme's back** through the web services is undone by the
    reconcile task (the preference is the record).
  - **No overlap with other component owners.** As `cohort_manager::get_cohorts()` skips cohorts
    stamped by other components, provisioning claims a cohort only when both its `idnumber` and its
    `component = 'theme_dimensions'` match. An `idnumber` already held by a cohort with another stamp,
    or with none, is left alone and reported, never taken over. Since tool_dynamic_cohorts offers no
    stamped cohort of another component to its rules, the two plugins cannot fight over one cohort.
- **Look cohorts in flavour rules.** Someone in look cohort X chose X, which wins anyway (F1), so a
  flavour rule on that cohort only ever shows to people whose choice already overrides it. Worse, every
  change of look would set Boost Union's never-cleared purge flag (section 3). The setting's
  description says not to; step 4 of section 4.2 absorbs the flag if it happens.
- **Privacy.** Core's `core_cohort` provider exports and deletes membership of any cohort, whatever its
  component (`cohort/classes/privacy/provider.php:69`). The theme's metadata links the subsystem, and
  the README names the cohorts.
- **Cost.** One cohort join per restricted menu or item, only on a cache miss of that person's menus. A
  membership write only when the look changes.

## 5. Stages

Each stage is one pull request on `main`, with `[skip ci]` and the local matrix as the gate
(fleet section 4).

**S1. Choice and priming, no UI.**
- What:
  - the setting and the preference;
  - `classes/local/flavourchoice.php` (the effective choice and the priming);
  - the hook callback and the `flavourchoice` cache definition;
  - lang strings in en and pt_br;
  - a version bump (`db/hooks.php` and `db/caches.php` both change).
- PHPUnit tests:
  - **The effective choice.** A listed flavour stands. An unlisted one, a deleted one, the guest and a
    visitor read as 0.
  - **A real page header** (as `core_renderer_test` does). The body class `flavour-<id>`, the flavour
    stylesheet URL, the navbar colour and the logo URL all follow the choice, and the choice beats a
    category flavour and a cohort flavour (F1). With choice 0, the category flavour is shown: that is
    the control.
  - **Invalidation.** The marker is refreshed by a flavour edit event, by a change of preference made in
    another session, and by the flavour leaving the list.
  - **The purge flag.** With the flag set, the choice survives two page headers. A drift test pins the
    defect.
- Mutation gates on every guard of steps 1, 2 and 5.

**S2. User menu and drawer.**
- What: the GET path of `flavour.php`, the `render_from_template()` branch, the `drawernav` panel, and
  the templates if the drawer needs one.
- Tests:
  - PHPUnit on the context: which items appear and which is active; nothing for the guest, a visitor
    or an empty setting; the drift guard on `usermenu.submenus`.
  - The sesskey refusal, and an off-site `returnurl` rejected.
  - One Behat smoke: choose a look in the user menu, and the body carries `flavour-<id>`.

**S3. Preferences page.**
- What: the form path of `flavour.php` and `theme_dimensions_extend_navigation_user_settings()`.
- Tests: PHPUnit on the node (shown only on `preferences.php`, and only for oneself) and a form round
  trip. Behat on the link from the preferences page.

**S4. Release work for S1-S3.**
- Privacy: the provider's `export_user_preferences()` and metadata gain the preference.
- The `README` names the setting, the preference and the dependency on the warm-up, which
  `marketplace_readiness_test` checks. Also `CHANGELOG.md`.
- Gates:
  - the `security-diff-read` skill over the whole diff (a preference, a state-changing GET, a
    redirect);
  - `mdl ci moodle-theme_dimensions --matrix --behat`;
  - an MDL Shield review only if the owner asks for one on that pull request.
- **Release only after the warm-up work is merged** (section 7). Merged: #52, #57, #59
  (`docs/flavourcss/PLAN.md`).

**S5. Smart menus by look** (section 4.7). It can ship with S4 or after it.
- What:
  - `classes/local/lookcohorts.php` (provision, empty, release, sync one person);
  - the setting's updated callback;
  - a scheduled reconcile task and an adhoc emptying task;
  - the `cohort_deleted` observer (`db/events.php`);
  - the membership write in `flavour.php`;
  - `db/uninstall.php` releasing the cohorts;
  - the privacy metadata link to `core_cohort`;
  - README, lang and a version bump (`db/tasks.php` is new).
- PHPUnit tests:
  - membership follows the choice, and switching leaves exactly one look cohort;
  - choice 0 leaves none;
  - an unlisted flavour's cohort is emptied and **not deleted**: the control is a menu restricted by
    it, still hidden from a non-member afterwards;
  - a smart menu restricted by a look cohort, built through `smartmenu::build_smartmenu()` for that
    person, appears and disappears with the choice on the very next build, with no stale cache entry;
  - deleting a look cohort through `core_cohort_delete_cohorts` leaves the menu it restricted hidden
    from a non-member. The control is the same deletion with the observer off, which opens the menu;
  - an `idnumber` held by another component's cohort is not claimed;
  - provisioning twice creates nothing new;
  - the reconcile task repairs a membership removed behind its back;
  - uninstall releases the cohorts and keeps their members.
- Mutation gates on the lock, the keep-instead-of-delete branch, the `component` stamp, the observer's
  priority and its hide call, and the claim check on `component`.

**U. Upstream (optional, any time, no merge by us).** Small Boost Union patches. Each one reduces
database traffic for every site, whatever this theme does (section 7.2):
1. Clear the flavour purge flag after it is honoured (section 3). The flag is then removed from
   section 4.2, step 4.
2. In `smartmenu::build_smartmenu()`, call `clear_user_cachepreferencemenu()` only when the flag is
   set. Today it runs on every build, and `set_user_preference()` reads the row before its early return
   (core `lib/moodlelib.php:1544`).
3. In `detect_lang_session_change()` (`classes/smartmenu.php:1015`), do not treat the first build of a
   session as a language change.
4. Cache an empty item list as "no items", so that a menu without items stops re-reading them on
   every page.

## 6. Why not other routes for the smart menus

- **Filtering the built menus in `render_from_template()`.** On 5.3 the main menu also reaches the page
  as React props (`export_react_props()`, `classes/output/navigation/primary.php:141`). One menu would
  have to be filtered in four structures: the main menu and its React props, the menubar, the user
  menu and the drawer.
- **Hiding with CSS.** The link stays in the page, and the "More" menu still counts the item: it is
  presentation, not a restriction.
- **A restriction hook upstream.** This is the clean long-term shape, but it depends on the maintainer,
  and it is bound by the same per-user cache.

## 7. Performance (measured on m503 2026-10-28)

### 7.1 The flavours

These measurements were taken from the CLI with Xdebug off. `get_css_content()` writes no cache, so
taking them changed nothing on the site.

| | Measure |
|---|---|
| Server cost per page (section 4.2) | In-memory reads; one `get_record` per session; Boost Union's scan skipped for people with a choice |
| Cold compile of one flavour | ~7 s CPU, ~120 MB peak (21-25 s with `xdebug.mode=debug`) |
| One flavour stylesheet | 2.84 MB, 431 KB gzip, cached per flavour and shared by everyone |
| Flavour "Cor" (brand colour only) against the main sheet | 145 of 23,518 rule blocks differ: 43 KB, 8 KB gzip |
| Browser | One 431 KB download the first time a flavour is chosen, then cached for the revision |

**The risk is the cold compile, not the choice.**
- `flavours/styles.php:189-190` takes one lock for the theme name, shared by every flavour and the main
  sheet. Waiters give up after 90-120 s and then compile anyway.
- After a purge, an upgrade, a theme setting change or a flavour save, people spread over N flavours
  queue behind one another, about 7 s per flavour, and waiters that time out compile in parallel at
  120 MB each.
- User choice makes many flavours hot at the same time, which is why the warm-up is a release
  prerequisite. It is merged: a scheduled task compiles every offered flavour within a minute or so of
  a reset, one child process per flavour (`docs/flavourcss/PLAN.md`; measured 23.2 s cold against
  0.013 s warmed). The design adds nothing to the compile: the stylesheet URL and its cache key are Boost
  Union's.

### 7.2 The smart menus, and why pre-warming their cache does not pay

Measured with `smartmenu::build_smartmenu()` as the admin, in one CLI process, on m503's one menu.
That menu has no items, which inflates the warm figure by one read (point 4 of stage U).

| Build | Reads | Writes |
|---|---|---|
| First of a session | 9 | 3 |
| Every later page of the session | 2 | 0 |
| `clear_user_cachepreferencemenu()` alone, which runs on every build | 1 | 0 |

**Pre-warming would buy nothing, for three reasons:**
- **Boost Union purges a person's menu cache at the start of every session.**
  `detect_lang_session_change()` treats the session's first build as a language change, because
  `$SESSION->theme_boost_union_prevlang` is unset. It sets the purge flag, and the same build deletes
  every menu and item entry of that person. A pre-warmed entry is deleted on the first page of the
  session before anything reads it: the 9 reads and 3 writes above are that rebuild.
- **The keys are per user.** Warming means building users × menus plus users × items entries,
  restrictions evaluated per person, mostly for people who will not come that day. That is Redis
  memory and a long database job spent to save nothing, because of the first reason.
- **What remains on a warm page cannot be warmed.** With the menus in the application cache (Redis
  under `mdl redis`), a warm page with real items reads one statement: the purge-flag clear, which
  calls `set_user_preference()`, which reads the preference row before it returns early. This is
  read from the code, not measured: m503 has no menu with items.

**What does cut database requests is stage U**, patches 1 to 4:
- Patch 2 makes a warm page read nothing.
- Patch 3 lets the per-user menu cache survive across sessions, turning the 9 + 3 per session into
  cache reads. A warm-up would then help only the first visit after a purge.
- Patch 1 stops a full flavour scan on every page for anyone who ever changed cohort under a cohort
  flavour.

These are Boost Union changes; a child theme cannot reach any of them. The infrastructure side is
already right on the stacks: sessions in Redis (the flavour caches are session caches), and the
application store, where the smart-menu caches live, mapped to Redis.

## 8. Risks

- **The organisation's flavour no longer has the last word (F1).** `CLAUDE.md` calls the flavour per
  organisation "the centre of the platform". With this feature on, it is the centre only for people who
  chose nothing. The administrator's control is the list (F2); the README says so.
- **A consumer that runs before `header()`.** It would fix Boost Union's `static` before the prime. None
  exists in Boost Union or in this theme today. The page-header test of S1 is the guard: if a future
  consumer moves earlier, the body class stops following the choice and the test reddens.
- **Requests without `header()`**, for example a web service that returns a logo URL. They show Boost
  Union's own answer, never the choice. This is accepted; the next page primes again.
- **A flavour's raw SCSS can break the page for whoever picks it.** That is already true for a category
  flavour. The setting's description says the listed flavours must work on every page.
- **Look cohorts are visible site-wide** (enrolment by cohort, reports). That is the price of reusing
  Boost Union's restriction. The README names them.

## 9. Defaults picked here (overturn before the stage starts)

| # | Default | Alternative |
|---|---|---|
| D1 | "Site default" means Boost Union's rules apply (the administrator's category and cohort flavours, or none) | "No flavour at all", overriding the administrator's rules too |
| D2 | Labels: "Look" / "Visual" (pt_br), "Site default" / "Padrão do site" | Other wording |
| D3 | **Changed in S2:** the menu writes through the theme's own web service from an AMD module, then reloads; without JavaScript each item opens `flavour.php`, which shows a POST form and writes nothing on the GET. The theme never changes state on a GET (`focus.php` says why), and one write path (`flavourchoice::choose()`) keeps S5's cohort writes in one place. | A GET with sesskey and a redirect |
| D4 | An empty setting means the feature is off; there is no separate on/off switch | A separate checkbox |
| D5 | "Site default" is always offered | Only the listed flavours |
| D6 | No cohort for "Site default" | A cohort for it too, so a menu can target people with no choice |
| D7 | Uninstall releases the look cohorts (`component` cleared, members kept) | Delete them, which opens every menu restricted by them alone |
