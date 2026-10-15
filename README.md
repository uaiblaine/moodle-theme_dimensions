moodle-theme_dimensions
=======================

[![Moodle Plugin CI](https://github.com/uaiblaine/moodle-theme_dimensions/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/uaiblaine/moodle-theme_dimensions/actions/workflows/ci.yml?query=branch%3Amain)

Dimensions is a Moodle theme: a child of
[Boost Union](https://github.com/moodle-an-hochschulen/moodle-theme_boost_union), which makes it a
grandchild of Boost. It inherits everything Boost Union does - every setting, every template, every
line of SCSS - and adds its own login page, category showcase, course landing pages, focus mode and
course notice on top of it.

It is the theme of the *dimensions* family of plugins, beside
[local_dimensions](https://github.com/uaiblaine/moodle-local_dimensions),
[block_dimensions](https://github.com/uaiblaine/moodle-block_dimensions) and
[block_compass](https://github.com/uaiblaine/moodle-block_compass), and it is meant to grow around
their user experience, Moodle's competency framework first (see the roadmap below).

The starting point is the official
[Boost Union Child](https://github.com/moodle-an-hochschulen/moodle-theme_boost_union_child)
boilerplate. The structure of `config.php`, `lib.php` and the SCSS inheritance settings comes from
there unchanged, so the upstream documentation about how a Boost Union child theme works applies to
this theme as well.


Requirements
------------

- Moodle 5.3. The theme requires `2026100500` and declares `$plugin->supported = [503, 503]`; it
  is not meant to be installed on any other release.
- PHP as Moodle 5.3 requires it.

### Required plugins

| Plugin | Version | Why |
|---|---|---|
| [theme_boost_union](https://github.com/moodle-an-hochschulen/moodle-theme_boost_union) | 2026100500 or later, its Moodle 5.3 version (the `upgrade-503` branch until Boost Union tags its 5.3 release) | The parent theme. |
| [local_unlistedcourses](https://github.com/uaiblaine/moodle-local_unlistedcourses) | 2026100505 or later | Decides which courses and categories are listed, unlisted or public; the category showcase, the public pages and the listings ask it. |

### Optional plugins

| Plugin | What it adds |
|---|---|
| [customfield_picture](https://github.com/uaiblaine/moodle-customfield_picture) (recommended) | The field type of the landing pages' four badges and their share picture. Without it, or with the Picture type disabled, those five fields are not created and nothing draws them; the theme's recommendation in Boost Union's overview says so. Install it later and use that recommendation's fix to create them. |
| auth_loginsteps | Its logo and identity provider data on the login page, its own shell for the second factor and privileged session pages, and the CPF mask of the login form (`logincpfmask`). The theme asks it by class name and never requires it. |
| [local_page](https://github.com/uaiblaine/moodle-local_page) | The pages strip on a category page. Without it the strip is simply not drawn. |


Installation
------------

Install the plugin like any other plugin, to the folder `theme/dimensions`. See
<https://docs.moodle.org/en/Installing_plugins> for the details.

After installation the theme looks and behaves like Boost Union. Select it in *Site administration >
Appearance > Themes > Theme selector* to activate it; its own settings live in *Site administration >
Appearance > Boost Union > Dimensions*.

Installing the theme creates two course custom field categories, *Course landing page* (the landing
page fields, their short names prefixed `dimensions_`) and *Focus mode* (one checkbox). They are created
once and never deleted; fields whose short names already exist on the site are left alone. The five
picture fields of *Course landing page* are created only where customfield_picture is installed and
enabled.

The theme assigns no brand colour of its own: the primary colour is Boost Union's (its brand colour
setting, or a flavour's) and Boost's default blue otherwise, and a category nobody branded draws its
band in that same default blue with white text.


Features
--------

### Custom login page

On by default (*Login page > Login page to show*). The theme draws the login page itself: an
institutional panel on the left - the logo, a kicker, a title and an optional monitoring notice -
and, on the right, the ways in, stacked with an "or" between them: the identity providers first,
then the username and password form, then "Create my account" whenever core's self registration is
on. The panel follows the whole login flow (password recovery, the MFA challenge and every other
page on the login layout).

- **No request to an identity provider before a choice.** Core renders each provider's image from
  the provider's own address, which makes every visitor's browser announce itself to it. This page
  draws known providers from the Font Awesome Brands font Moodle already ships, and shows a
  provider it has no mark for by name only, unless its image is served by this site.
- **A reset hint after the second failed login in a row**, counted per browser session and never per
  account, so it reveals nothing about which usernames exist.
- **The panel's copy is a setting** (*Login page > Institutional panel*); a field left empty shows its
  language string, which follows each user's language.
- While the page is on, Boost Union's login page settings that shape the form are not read; core's
  own switches (`showloginform`, the enabled authentication plugins, `guestloginbutton`,
  `registerauth`) decide what is drawn. *Leave it to Boost Union* gives Boost Union's page back
  exactly as configured there.

### Category showcase

With *Category showcase > Show the category showcase* on, `/course/index.php?categoryid=N` is the
theme's own page: a hero with the category's name, tagline, description, counts and tabs, then the
courses of the category and its subcategories as cards or a dense list, with search, state chips,
filter groups built from course custom fields, a start-date filter, a category tree and real paging.
It works with JavaScript off; with JavaScript on, every choice is applied in place. The listing is
organised **by area** (one poster per subcategory, whose courses open in a panel between the rows),
**A-Z** or **Recent**. Each card shows the enrolment state for the reader, a favourite star and a
"More information" dialogue.

- A category manager styles the hero from the *Category appearance* tab: layout, background image,
  a free colour pair refused below the WCAG AA contrast of 4.5:1, up to three crests, a card cover
  and a short cover video that plays muted on hover.
- `/course/index.php` without a category is a **directory** of the top-level categories.
- A category local_unlistedcourses holds **public** has a page for visitors who are not logged in,
  with forced login left on, and the root directory can be opened to visitors too; both are off
  until an administrator switches them on. A category held **unlisted** is withheld from every
  listing the theme renders.

### Course landing pages

An enrolment page for each course that opts in, at `/theme_dimensions/landing/<courseid>` on a site
whose router is configured (and at `/theme/dimensions/landing.php?id=<courseid>` otherwise). A course
opts in by choosing a page model (*Course* or *Event*) in the *Course landing page* custom fields of its
settings, which also hold the modality,
workload, audience, programme, certification, venue, a reviewed public description, a presentation
video address, up to four badges and a share picture. The page shows only the enrolment methods
the current user can actually use, and `/enrol/index.php?id=N` forwards to it for a course with a
model. Each page gets a short public address that can be dictated over the phone
(`https://<site>/p/batepo`) once Moodle's router is configured.

### Focus mode

An activity page opens in a reduced shell: a bar carrying the activity name where the navigation bar
normally is, the course index and the block drawer kept, no breadcrumb and no tabs. A course switches
it on with its *Focus mode on activities* checkbox; in a course that did not, a *Focus mode* button in
the navigation bar opens that course's activities focused for the rest of the browser session. The reading width is remembered per user.
Quiz attempt pages are excluded by default, and `?focusoff=1` renders any one request with the
ordinary theme.

### Course notice

One notice region replaces Boost Union's course related hints on every course page, under the same
Boost Union settings, capabilities and texts: a bar of chips that sums the course's state up, with
the details under it. For whoever edits the course, a course with a landing page gets a
*Promotion* section with its public addresses and copy buttons.

### Profile access rule

A person may open another person's profile only when they can see every profile, when they share a
course whose participant list they may see, or when they come from the page of a course in which the
other person is a course contact. The rule only ever narrows core's answer, and it applies while
core's `forceloginforprofiles` is on (Boost Union's recommendations overview says whether it is).

### Guest access

A course whose guest access needs no password gets an *Enter as guest* address beside its landing
page's: a visitor lands on the course as the guest account in one step, while link previews never
open a guest session. The login page tab also sets where the guest account goes from the site's
home page (*Guest home page*).

### Open Graph

The public landing page and the public category page carry Open Graph tags (title, the reviewed
public description, a 1200x630 preview image, canonical URL, site name, locale), so a shared link
unfurls in messaging apps and social networks. They are `noindex` unless the site is open to search
engines.

### Pages strip

With local_page installed, a category page names that category's pages under its hero, as the
plugin's own rules let the reader see them.

### Settings tabs

*Site administration > Appearance > Boost Union > Dimensions*:

| Tab | What it holds |
|---|---|
| General settings | Boost Union Child's two SCSS inheritance settings; leave them on *Inherit*. |
| Login page | The login page switch, the institutional panel's copy and the guest home page. |
| Identity | The navbar style: dark in the brand colour with a 3px signature band, or *Leave it to Boost Union*. |
| Focus mode | The master switch, the default width, the excluded page types and the default for new courses. |
| Category showcase | The showcase switch, the public pages and their live listing, the page size, the grouping by state, the tree depth, the filter groups, the cover video size limit and the site-wide hero image. |


Brazil-specific today
---------------------

The theme was built for a Brazilian site. The CPF username mask it once carried lives in the
authentication plugin `auth_loginsteps` now (its setting `logincpfmask`, with the same `?nocpf=1` way
back to a plain form): the theme draws the login page and keeps the form hooks that mask binds to,
and nothing of the mask itself.


Capabilities and privacy
------------------------

The theme declares no capabilities of its own; its settings page is guarded by Boost Union's
`theme/boost_union:configure`. It stores one table (the branding of a category, no personal data)
and five user preferences (display choices such as the focus mode width and the category view),
which its privacy provider describes and exports.


Roadmap
-------

- **Boost Union's 5.3 release**: the theme follows Boost Union's `upgrade-503` branch until Boost Union
  tags its 5.3 version, then pins that release.
- **The competency framework's user experience** across the dimensions family of plugins
  (local_dimensions, block_dimensions, block_compass), with this theme as its home.


Credits
-------

Built on [Boost Union](https://github.com/moodle-an-hochschulen/moodle-theme_boost_union) and on the
[Boost Union Child](https://github.com/moodle-an-hochschulen/moodle-theme_boost_union_child)
boilerplate by moodle-an-hochschulen (Moodle an Hochschulen e.V.), Alexander Bias, Daniel
Poggenpohl and Lars Bonczek. The Behat step definition overrides are theirs as well.


License
-------

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).

Copyright: 2026 Anderson Blaine
