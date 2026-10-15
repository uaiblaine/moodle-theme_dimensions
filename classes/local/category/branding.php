<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Theme Dimensions - The branding of a course category on the category showcase
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use core\context;
use core_course_category;
use local_unlistedcourses\category_access;
use theme_dimensions\event\category_branding_updated;
use theme_dimensions\local\landing\publicfiles;
use theme_dimensions\local\landingfields;

/**
 * The hero of a category: its layout, colours, overlay, tagline, public text and background image.
 *
 * One row per branded category in {theme_dimensions_cat}, and the file areas below in
 * the category's own context. A category without a row reads the defaults: the solid band
 * in the default dark blue and no tagline. usermodified is the id of the user who last saved the row, kept as an
 * authorship stamp and declared in the privacy provider; the table holds nothing about the people
 * who read the page.
 *
 * The colours are a free pair - a background and an ink, both #rrggbb - so that a category can
 * carry its own institutional colour. Two things keep that from publishing an illegible hero.
 * {@see hex()} is the only way a value enters or leaves this class, which is what lets the hero
 * emit an inline style at all: a string that is not a hex colour becomes the default, on the way
 * in and again on the way out. And {@see validate()} refuses a pair whose {@see contrast()} is
 * under {@see FLOOR}, whatever called {@see save()} - WCAG AA for normal text, which the tagline
 * and the description are. The only colours this class names are the default pair.
 *
 * A category without a pair of its own takes the pair of its nearest ancestor that has one, and the
 * theme's default pair when none has ({@see colours()}). The pair travels whole: it was certified
 * as a pair, so a background is never taken from one category and an ink from another. Nothing
 * else is inherited - the layout, the overlay, the texts and every file are the category's own,
 * the overlay because it is tuned to the category's own photograph.
 *
 * The row and the name of the image are the one thing the showcase caches across requests:
 * viewer-independent, holding no count and no course name, keyed by category id. The cached pair
 * is the RESOLVED one, so an entry depends on the rows of every ancestor: the whole cache is
 * dropped when the editor saves a row, when a row is deleted and when core reports a category
 * updated, which is the event a move fires ({@see forget_all()}). {@see CACHED_KEYS} is the whole
 * payload and a test pins it, so a count can never creep into it.
 *
 * The files are served through the theme's own file route ({@see serve()}), and nothing about
 * a request is trusted: the route re-asks, for a logged-in requester, core whether the
 * category may be listed for this viewer and then the plugin - the two questions the page
 * itself asks - and refuses everything else with a bare 404. A visitor is asked the one
 * question the anonymous shell asks, {@see publicaccess::is_public()}, which fails closed and
 * runs before any lookup: the route is reachable exactly when the page is, for both classes
 * of viewer, and neither can be widened without widening the other.
 *
 * Seven route areas, all addressed with the category id as the pluginfile item id; the six that
 * hold a file store it at item 0. categoryhero serves the stored bytes to whoever may see the page. categorycover and
 * categorycovervideo are the category's card: a still cropped to the card's frame, different
 * from the hero's background, and a short muted film played over it while a pointer rests on
 * the card. The video is the one area whose bytes are graded rather than trusted
 * ({@see is_valid_video()}), because core has no video validator and derives a mimetype from the
 * file name. categorybadge1..3 serve the category's own crests, one file each, under the same
 * admission and the same image check as the hero ({@see resolve()}). categoryogimage serves the
 * 1200x630 JPEG derivative a social scraper fetches - the landing page's own derivative machinery
 * ({@see publicfiles}), reused unchanged - and it falls back to the site-wide image when the
 * category has none of its own, because a preview with no picture is what the fallback exists to
 * prevent. Its URL names the category's context either way: the route resolves by category id,
 * and the site-wide file lives in a context a category URL must never have to name.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class branding {
    /** @var string The component the file URLs carry and the cache belongs to. */
    public const COMPONENT = 'theme_dimensions';

    /** @var string The table. */
    public const TABLE = 'theme_dimensions_cat';

    /** @var string The hero image area, in the category's context, item 0. */
    public const AREA_HERO = 'categoryhero';

    /**
     * @var string[] The three badge areas, in display order; one file each, in the category's context, item 0.
     *
     * Three areas and not one area with three item ids, because of the URL shape this route
     * already has: {@see image_url()} passes the category id as the pluginfile item id and
     * {@see resolve()} reads it back out of the path and compares it with the context's own
     * instance. An item id carrying 1..3 would break that pairing and cost a fourth path segment.
     * The area name is therefore the badge's position, ordering and gap-closing are the order of
     * this list ({@see badges()}), and the editor names its three file managers after the areas.
     */
    public const AREA_BADGES = ['categorybadge1', 'categorybadge2', 'categorybadge3'];

    /**
     * @var string The card cover image, in the category's context, item 0, one file.
     *
     * A different file from the hero's background image: a band cropped to a 320x150 card and a
     * band behind a page title are not the same picture. The card falls back to the hero image
     * when no cover was uploaded ({@see population::directory()}), so a category with no cover
     * keeps showing its hero image.
     */
    public const AREA_COVER = 'categorycover';

    /**
     * @var string The card cover video, in the category's context, item 0, one file.
     *
     * Played muted on hover over the card's still. Never trusted by its name: {@see is_valid_video()}
     * reads the file's first bytes, and a file that fails that check is absent from the cached
     * payload and refused by the route.
     */
    public const AREA_COVERVIDEO = 'categorycovervideo';

    /**
     * @var string[] What a cover video may be: file extension => the mimetype the route serves.
     *
     * The extensions are what the editor's file manager accepts; the mimetypes are what
     * {@see video_type()} answers for a matched signature and what {@see serve()} sends. The two
     * halves are deliberately separate, because core derives a stored file's mimetype from its
     * name - `mimeinfo()` over the extension - and has no video validator at all, so a text file
     * called `capa.mp4` is `video/mp4` to every core reader of that record.
     */
    public const VIDEO_TYPES = ['mp4' => 'video/mp4', 'webm' => 'video/webm'];

    /** @var string The setting bounding a cover video's size. */
    public const SETTING_COVERVIDEO_MAXBYTES = 'categorycovervideomaxbytes';

    /** @var int The size a cover video may reach while nobody chose another: 20 MB. */
    public const DEFAULT_COVERVIDEO_MAXBYTES = 20971520;

    /** @var int How many bytes of a file the signature check reads. */
    private const SIGNATURE_BYTES = 12;

    /** @var string The Open Graph derivative of the hero image, in the category's context. */
    public const AREA_OGIMAGE = 'categoryogimage';

    /** @var string File name of the Open Graph derivative, the last segment of its URL. */
    public const OG_FILENAME = 'og.jpg';

    /** @var string The site-wide fallback image area, in the system context: the setting's own file area. */
    public const AREA_FALLBACK = 'categoryherofallback';

    /** @var string The setting holding the site-wide fallback image. */
    public const SETTING_FALLBACK = 'categoryherofallback';

    /** @var string The cache definition of db/caches.php. */
    public const CACHE = 'categorybranding';

    /** @var string A band with the photograph behind the colour overlay. */
    public const LAYOUT_IMAGE = 'image';

    /** @var string A band in the solid colour. */
    public const LAYOUT_SOLID = 'solid';

    /** @var string The title row and the tabs alone. */
    public const LAYOUT_COMPACT = 'compact';

    /** @var string[] Every layout the editor offers. */
    public const LAYOUTS = [self::LAYOUT_IMAGE, self::LAYOUT_SOLID, self::LAYOUT_COMPACT];

    /** @var string The layout of a category nobody branded. */
    public const DEFAULT_LAYOUT = self::LAYOUT_SOLID;

    /**
     * @var string The band colour of a category nobody branded: a dark blue, 9.01:1 with white ink.
     *
     * A hex and not var(--bs-primary), because the stops, the watermark's alpha and the contrast
     * audit are computed from its channels here. White ink on it reads 9.01:1, over {@see FLOOR}.
     */
    public const DEFAULT_BG = '#0a4a85';

    /** @var string The ink of a category nobody branded. */
    public const DEFAULT_INK = '#ffffff';

    /**
     * @var float The contrast a stored pair must reach: WCAG 2.x AA for normal text.
     *
     * The tagline and the public description are normal-size text on the band, so 4.5:1 is the
     * floor and not 3:1. The editor's panel shows the ratio and offers fixes; this is the
     * refusal behind it, deliberately a refusal and not a warning.
     */
    public const FLOOR = 4.5;

    /** @var float How much darker the first gradient stop is than the band colour. */
    private const STOP_DARKEN = 0.18;

    /** @var float How much of white the last gradient stop mixes into the band colour. */
    private const STOP_LIGHTEN = 0.12;

    /**
     * @var int[] How much of the colour may cover the photograph, in percent.
     *
     * 0 is "Transparent": the three gradient stops become fully transparent and the photograph is
     * painted alone, so nothing measured by the contrast panel applies to the text over it - the
     * help text says so. The tab strip keeps the opaque mid stop at every overlay
     * ({@see \theme_dimensions\output\category_hero::style()}), so the tabs read on a
     * bare photograph whatever this is set to.
     */
    public const OVERLAYS = [0, 60, 78, 90];

    /** @var int The overlay of a category nobody branded. */
    public const DEFAULT_OVERLAY = 78;

    /** @var int The longest tagline: one sentence. */
    public const TAGLINE_MAXLENGTH = 160;

    /**
     * @var int The longest card text: what the card's excerpt of the description is cut at.
     *
     * The same number as {@see population::EXCERPT_LENGTH}, so a card with a text of its own holds
     * no more than a card falling back to the description would, and the card's box is sized for
     * one of them. The column is char(160) and the editor's field carries it as a maxlength.
     */
    public const CARDSUMMARY_MAXLENGTH = 160;

    /**
     * @var int The longest public description: what a share preview and a search result show.
     *
     * The column is char(200) and the editor's field carries the same number as a maxlength
     * attribute, so the browser stops at it before anything is submitted; this is what the form
     * and {@see validate()} refuse past, for every other caller and for a browser that ignores
     * the attribute.
     */
    public const PUBLICSUMMARY_MAXLENGTH = 200;

    /**
     * @var string[] The keys of the cached payload, in order - the whole payload.
     *
     * Pinned by a test: whatever is added here is cached across requests for every viewer,
     * so it may hold nothing a viewer's rights decide, no count and no course name.
     */
    public const CACHED_KEYS = [
        'layout',
        'bgcolour',
        'inkcolour',
        'owncolours',
        'colourfrom',
        'overlay',
        'tagline',
        'publicsummary',
        'cardsummary',
        'defaultsort',
        'publicdescription',
        'publicdescriptionformat',
        'image',
        'cover',
        'covervideo',
        'badges',
    ];

    /** @var int Lifetime of an answer of the image route, whose URL carries the content hash. */
    private const HASHED_LIFETIME = 7 * DAYSECS;

    /**
     * A colour value as the one spelling this plugin stores and emits, or null.
     *
     * The one sanitiser of every colour in this feature: nothing reaches a stylesheet, a form
     * or the database without passing here, so the hero's inline style can only ever hold a
     * hex colour. The leading "#" is required and the three-digit form is accepted, which is
     * exactly the vocabulary of the editor's own module (its HEXRE) and of the help text and
     * the error message the manager reads ("#rrggbb"); the two are pinned against each other
     * by the `spellings` table of tests/fixtures/contrast_pairs.json, because a sanitiser the
     * panel and the server disagree about grades a value one of them would refuse. Everything
     * else - an empty string, a name, a declaration, a value with anything after it - is null,
     * and the caller decides between a default and a refusal.
     *
     * @param string $value Whatever was typed, stored or handed over.
     * @return string|null Lower-case #rrggbb, or null when the value is not a hex colour.
     */
    public static function hex(string $value): ?string {
        $value = trim($value);
        if (!preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', $value, $matches)) {
            return null;
        }
        $digits = strtolower($matches[1]);
        if (strlen($digits) === 3) {
            $digits = $digits[0] . $digits[0] . $digits[1] . $digits[1] . $digits[2] . $digits[2];
        }
        return '#' . $digits;
    }

    /**
     * An overlay value as one of {@see OVERLAYS}, or null.
     *
     * Compared before the cast, which is the point of the method: with 0 ("Transparent") in the
     * vocabulary, `(int) $value` maps '', null, 'banana' and 'transparent' onto a valid overlay,
     * so a value outside the vocabulary would be stored as "no colour at all" rather than
     * refused. A select hands an unknown choice back as ''. The appearance form validates with this
     * reader too, so the vocabulary has one.
     *
     * @param mixed $value Whatever was submitted, stored or handed over.
     * @return int|null The overlay, or null when the value is not one of them.
     */
    public static function overlay(mixed $value): ?int {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || !ctype_digit($value)) {
                return null;
            }
            $value = (int) $value;
        }
        if (!is_int($value)) {
            return null;
        }
        return in_array($value, self::OVERLAYS, true) ? $value : null;
    }

    /**
     * A category's default organisation as one of {@see criteria::SORTS}, or null.
     *
     * The vocabulary is the address's own, so a default can be nothing an address could not ask
     * for. '' is the theme's default, which is the grouped organisation today; 'category' names the
     * grouped organisation explicitly, and stays that if the theme's default ever changes.
     *
     * @param string $value Whatever was submitted or stored.
     * @return string|null The organisation, or null when the value is not one.
     */
    public static function sort_choice(string $value): ?string {
        $value = trim($value);
        return in_array($value, criteria::SORTS, true) ? $value : null;
    }

    /**
     * The 8-bit channels of a hex colour.
     *
     * @param string $hex A value {@see hex()} accepts.
     * @return int[]|null [r, g, b], or null when the value is not a hex colour.
     */
    private static function channels(string $hex): ?array {
        $hex = self::hex($hex);
        if ($hex === null) {
            return null;
        }
        return [
            (int) hexdec(substr($hex, 1, 2)),
            (int) hexdec(substr($hex, 3, 2)),
            (int) hexdec(substr($hex, 5, 2)),
        ];
    }

    /**
     * The relative luminance of an sRGB colour, WCAG 2.x.
     *
     * @param int[] $channels [r, g, b], each 0-255.
     * @return float The luminance, 0-1.
     */
    private static function luminance(array $channels): float {
        $linear = [];
        foreach ($channels as $channel) {
            $c = $channel / 255;
            $linear[] = $c <= 0.03928 ? $c / 12.92 : (float) (((($c + 0.055) / 1.055)) ** 2.4);
        }
        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }

    /**
     * The WCAG 2.x contrast ratio of two colours.
     *
     * The mirror of the editor's own module, which grades the same pair live while it is being
     * typed: tests/fixtures/contrast_pairs.json is read by a test here and by the module's
     * source test, so the two cannot drift apart without one of them going red. A value that is
     * not a hex colour reads as 1.0 - the worst possible - so that every caller comparing
     * against {@see FLOOR} fails closed.
     *
     * @param string $bg The background colour.
     * @param string $ink The text colour.
     * @return float The ratio, 1-21.
     */
    public static function contrast(string $bg, string $ink): float {
        $first = self::channels($bg);
        $second = self::channels($ink);
        if ($first === null || $second === null) {
            return 1.0;
        }
        $one = self::luminance($first);
        $two = self::luminance($second);
        return (max($one, $two) + 0.05) / (min($one, $two) + 0.05);
    }

    /**
     * One colour laid over another at an opacity, as the lower-case #rrggbb this class speaks.
     *
     * What a translucent layer composites to over an opaque one, channel by channel, so that
     * {@see contrast()} can grade text against it: the poster's watermark is the ink laid over
     * the band ({@see population::mark_alpha()}). 0 gives the base and 1 the top; anything
     * outside is clamped. The arithmetic is done in whole ten-thousandths and rounds half up,
     * as the design prototype's Math.round() does on a positive number, because PHP's round()
     * of a float lying just under .5 differs between 8.3 and 8.4.
     *
     * It has no default: a value {@see hex()} refuses on either side is null, which the caller's
     * contrast() then reads as 1.0 and fails closed.
     *
     * @param string $base The colour underneath.
     * @param string $top The colour laid over it.
     * @param float $ratio The top colour's opacity, 0-1.
     * @return string|null The composite, or null when either colour is not a colour.
     */
    public static function mix(string $base, string $top, float $ratio): ?string {
        $under = self::channels($base);
        $over = self::channels($top);
        if ($under === null || $over === null) {
            return null;
        }
        $weight = (int) round(max(0.0, min(1.0, $ratio)) * 10000);
        $mixed = [];
        foreach ($under as $index => $channel) {
            $mixed[] = intdiv($channel * (10000 - $weight) + $over[$index] * $weight + 5000, 10000);
        }
        return vsprintf('#%02x%02x%02x', $mixed);
    }

    /**
     * A contrast ratio as the editor prints it: two decimals, one from ten up.
     *
     * @param float $ratio The ratio.
     * @return string The number alone, without the ":1" the strings add.
     */
    public static function format_ratio(float $ratio): string {
        return $ratio >= 9.995 ? number_format($ratio, 1, '.', '') : number_format($ratio, 2, '.', '');
    }

    /**
     * The three gradient stops of a band colour.
     *
     * The band is one colour with a darker head and a lighter tail, derived so that any colour
     * reads as a gradient rather than a flat slab. The mid stop is the colour itself, and it is
     * what the tab strip paints - an opaque tone under white tabs, whatever the photograph
     * behind it.
     *
     * @param string $bg The band colour; anything {@see hex()} refuses yields the default's stops.
     * @return array Keys from, mid and to, each a lower-case #rrggbb.
     */
    public static function stops(string $bg): array {
        $channels = self::channels($bg) ?? self::channels(self::DEFAULT_BG);
        $from = [];
        $to = [];
        foreach ($channels as $channel) {
            $from[] = (int) round($channel * (1 - self::STOP_DARKEN));
            $to[] = (int) round($channel + (255 - $channel) * self::STOP_LIGHTEN);
        }
        return [
            'from' => vsprintf('#%02x%02x%02x', $from),
            'mid' => vsprintf('#%02x%02x%02x', $channels),
            'to' => vsprintf('#%02x%02x%02x', $to),
        ];
    }

    /**
     * A colour as an rgba() declaration value at a given opacity.
     *
     * The image layout paints the band over a photograph, so its stops are translucent; the
     * channels come from {@see channels()}, so the string this returns is as sanitised as the
     * hex it was built from and the hero's inline style stays a closed vocabulary.
     *
     * @param string $hex The colour; anything {@see hex()} refuses yields the default's channels.
     * @param float $alpha The opacity, 0-1; anything outside is clamped.
     * @return string An rgba() value, e.g. "rgba(7, 0, 147, 0.78)".
     */
    public static function rgba(string $hex, float $alpha): string {
        $channels = self::channels($hex) ?? self::channels(self::DEFAULT_BG);
        $alpha = max(0.0, min(1.0, $alpha));
        return 'rgba(' . $channels[0] . ', ' . $channels[1] . ', ' . $channels[2] . ', '
            . rtrim(rtrim(number_format($alpha, 2, '.', ''), '0'), '.') . ')';
    }

    /**
     * The branding of a category nobody branded.
     *
     * colourfrom is not a column: it is the category the pair comes from, 0 for the theme's
     * default pair, and it is here so that the root's payload, built from these values, has it.
     *
     * @param int $categoryid The category.
     * @return \stdClass A row-shaped object with id 0.
     */
    public static function defaults(int $categoryid): \stdClass {
        return (object) [
            'id' => 0,
            'categoryid' => $categoryid,
            'layout' => self::DEFAULT_LAYOUT,
            'bgcolour' => self::DEFAULT_BG,
            'inkcolour' => self::DEFAULT_INK,
            'owncolours' => 0,
            'colourfrom' => 0,
            'overlay' => self::DEFAULT_OVERLAY,
            'tagline' => '',
            'publicsummary' => '',
            'cardsummary' => '',
            'defaultsort' => '',
            'publicdescription' => '',
            'publicdescriptionformat' => FORMAT_HTML,
            'usermodified' => 0,
            'timecreated' => 0,
            'timemodified' => 0,
        ];
    }

    /**
     * The stored row of a category, uncached, with every choice read back into the vocabulary.
     *
     * A stored value outside the vocabulary - a layout a later version retired, a colour a
     * migration left malformed - reads as the default rather than failing the page.
     *
     * @param int $categoryid The category.
     * @return \stdClass The row, or {@see defaults()} when there is none.
     */
    public static function record(int $categoryid): \stdClass {
        global $DB;

        $row = $DB->get_record(self::TABLE, ['categoryid' => $categoryid]);
        if (!$row) {
            return self::defaults($categoryid);
        }
        $row->categoryid = (int) $row->categoryid;
        $row->layout = in_array($row->layout, self::LAYOUTS, true) ? $row->layout : self::DEFAULT_LAYOUT;
        $row->bgcolour = self::hex((string) $row->bgcolour) ?? self::DEFAULT_BG;
        $row->inkcolour = self::hex((string) $row->inkcolour) ?? self::DEFAULT_INK;
        $row->owncolours = empty($row->owncolours) ? 0 : 1;
        $row->overlay = self::overlay($row->overlay) ?? self::DEFAULT_OVERLAY;
        $row->tagline = (string) $row->tagline;
        $row->publicsummary = trim((string) $row->publicsummary);
        $row->cardsummary = trim((string) $row->cardsummary);
        $row->defaultsort = self::sort_choice((string) $row->defaultsort) ?? '';
        $row->publicdescription = (string) $row->publicdescription;
        $row->publicdescriptionformat = (int) $row->publicdescriptionformat;
        return $row;
    }

    /**
     * The colour pair each category draws: its own, else its nearest ancestor's, else the theme's.
     *
     * ONE statement however many categories are asked about: every category is joined to its
     * ancestors-or-self through the stored paths, and to the rows of those that have a pair of their
     * own (owncolours = 1); the deepest such row wins, so a subcategory's own pair overrides its
     * parent's. A grid of cards asks this once for all of them ({@see for_categories()}), never once
     * per card. Visibility is not asked: a colour is presentation, and a hidden parent's pair reaches
     * its visible child exactly as a visible parent's does.
     *
     * The pair is taken WHOLE from one row. It was certified as a pair ({@see validate()}), and a
     * background from one category beside an ink from another is a combination nobody measured. Each
     * half still passes {@see hex()} on the way out, as {@see record()} reads it, so a malformed
     * stored half reads as the default's half rather than reaching a style attribute.
     *
     * @param int[] $categoryids The categories; 0 (the root of the course index) has no row and no
     *   ancestor, and answers the theme's pair.
     * @return array categoryid => ['bgcolour' => string, 'inkcolour' => string, 'from' => int], from
     *   being the category the pair belongs to (the category itself for its own pair) or 0 for the
     *   theme's default pair.
     */
    public static function colours(array $categoryids): array {
        global $DB;

        $result = [];
        $ids = [];
        foreach ($categoryids as $categoryid) {
            $categoryid = (int) $categoryid;
            $result[$categoryid] = ['bgcolour' => self::DEFAULT_BG, 'inkcolour' => self::DEFAULT_INK, 'from' => 0];
            if ($categoryid > 0) {
                $ids[$categoryid] = $categoryid;
            }
        }
        if (!$ids) {
            return $result;
        }
        [$insql, $params] = $DB->get_in_or_equal(array_values($ids), SQL_PARAMS_NAMED, 'dimcolour');
        // An ancestor's path is a prefix of the category's own, up to a slash; the wildcard is bound.
        $below = $DB->sql_like('c.path', $DB->sql_concat('a.path', ':dimbelow'));
        $params['dimbelow'] = '/%';
        $pairid = $DB->sql_concat('c.id', "'-'", 'a.id');
        $sql = "SELECT $pairid AS pairid, c.id AS categoryid, a.id AS sourceid, a.depth, b.bgcolour, b.inkcolour
                  FROM {course_categories} c
                  JOIN {course_categories} a ON (a.id = c.id OR $below)
                  JOIN {" . self::TABLE . "} b ON b.categoryid = a.id AND b.owncolours = 1
                 WHERE c.id $insql";
        $depths = [];
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $categoryid = (int) $row->categoryid;
            if (isset($depths[$categoryid]) && $depths[$categoryid] >= (int) $row->depth) {
                continue;
            }
            $depths[$categoryid] = (int) $row->depth;
            $result[$categoryid] = [
                'bgcolour' => self::hex((string) $row->bgcolour) ?? self::DEFAULT_BG,
                'inkcolour' => self::hex((string) $row->inkcolour) ?? self::DEFAULT_INK,
                'from' => (int) $row->sourceid,
            ];
        }
        return $result;
    }

    /**
     * The branding the hero draws, cached across requests per category.
     *
     * {@see for_categories()} for one category, which is what the hero and every single reader ask.
     *
     * @param int $categoryid The category.
     * @return array The payload, exactly the keys of {@see CACHED_KEYS}; see {@see for_categories()}.
     */
    public static function for_category(int $categoryid): array {
        return self::for_categories([$categoryid])[$categoryid];
    }

    /**
     * The branding of several categories, cached across requests per category, with ONE colour lookup for the misses.
     *
     * Seven statements per miss - the row, the hero area, the cover, the cover video and the
     * three badge areas - plus one statement for all the misses together, which resolves their
     * colours ({@see colours()}); none while warm. A grid of cards asks this once for every card it
     * draws, so the inheritance of the colours costs the grid one statement and never one per card.
     * bgcolour and inkcolour are the RESOLVED pair and colourfrom the category it belongs to (0 for
     * the theme's); owncolours says whether the category has a pair of its own.
     *
     * The image and the cover are named only when core reads them as valid images, so an inline
     * .html in the area is never linked. The cover video is named only when its first bytes are an
     * MP4 `ftyp` box or an EBML header ({@see video_type()}), so a failed file is never named on a
     * page and never fetched: a `<video>` element pointing at a 404 is a control that looks broken
     * rather than a picture that failed to load.
     *
     * A badge is named whatever its bytes are; the route refuses a file that is not an image
     * ({@see resolve()}, which also says why an SVG crest passes). Such a file is named on the
     * page and answers 404 - a broken picture rather than a hole. It is reachable only by
     * writing through the file API (the editor's file managers accept images only), and hiding
     * it here would change the cached shape for a case nothing on screen can produce.
     *
     * @param int[] $categoryids The categories; 0 is the root of the course index.
     * @return array categoryid => the payload, exactly the keys of {@see CACHED_KEYS}; image and
     *   cover are ['contextid' => int, 'contenthash' => string, 'filename' => string] or null,
     *   covervideo is that shape plus mimetype or null, and badges is a map of area name => an entry
     *   of the image shape, in area order with gaps closed.
     */
    public static function for_categories(array $categoryids): array {
        $wanted = [];
        foreach ($categoryids as $categoryid) {
            $wanted[(int) $categoryid] = (int) $categoryid;
        }
        $payloads = [];
        if (isset($wanted[0])) {
            $payloads[0] = self::root_payload();
            unset($wanted[0]);
        }
        if (!$wanted) {
            return $payloads;
        }
        $cache = \core_cache\cache::make(self::COMPONENT, self::CACHE);
        $missing = [];
        foreach ($cache->get_many(array_values($wanted)) as $categoryid => $payload) {
            if (is_array($payload)) {
                $payloads[(int) $categoryid] = $payload;
            } else {
                $missing[] = (int) $categoryid;
            }
        }
        if ($missing) {
            $colours = self::colours($missing);
            $fresh = [];
            foreach ($missing as $categoryid) {
                $fresh[$categoryid] = self::build($categoryid, $colours[$categoryid]);
            }
            $cache->set_many($fresh);
            $payloads += $fresh;
        }
        return $payloads;
    }

    /**
     * The payload of the root of the course index, which has nothing to read and nothing to cache.
     *
     * The pseudo-category has no row, no context and no file area: the defaults, built here rather
     * than looked up, because coursecat::instance(0) does not exist and a get_record() on
     * categoryid 0 would be a statement that can only ever miss.
     *
     * @return array The payload, exactly the keys of {@see CACHED_KEYS}.
     */
    private static function root_payload(): array {
        $payload = [];
        $defaults = self::defaults(0);
        foreach (self::CACHED_KEYS as $key) {
            $payload[$key] = match ($key) {
                /* No file area of its own, so no image of its own - but the image layout,
                   which is what makes the hero paint the site-wide fallback behind the
                   default band colour. The hero falls back to the solid band by itself when no
                   fallback has been uploaded ({@see fallback_url()} answers null). */
                'layout' => self::LAYOUT_IMAGE,
                'image' => null,
                'cover' => null,
                'covervideo' => null,
                'badges' => [],
                default => $defaults->$key,
            };
        }
        return $payload;
    }

    /**
     * The payload of one category, read from the database and the file areas: what a cache miss costs.
     *
     * @param int $categoryid The category.
     * @param array $colours Its entry of {@see colours()}: the resolved pair and where it comes from.
     * @return array The payload, exactly the keys of {@see CACHED_KEYS}.
     */
    private static function build(int $categoryid, array $colours): array {
        $row = self::record($categoryid);
        $file = self::image($categoryid);
        $cover = self::image($categoryid, self::AREA_COVER);
        $video = self::image($categoryid, self::AREA_COVERVIDEO);
        $videotype = $video === null ? null : self::video_type($video);
        $badges = [];
        foreach (self::badges($categoryid) as $area => $badge) {
            $badges[$area] = [
                'contextid' => (int) $badge->get_contextid(),
                'contenthash' => $badge->get_contenthash(),
                'filename' => $badge->get_filename(),
            ];
        }
        return [
            'layout' => $row->layout,
            'bgcolour' => $colours['bgcolour'],
            'inkcolour' => $colours['inkcolour'],
            'owncolours' => (int) $row->owncolours,
            'colourfrom' => (int) $colours['from'],
            'overlay' => $row->overlay,
            'tagline' => $row->tagline,
            'publicsummary' => $row->publicsummary,
            'cardsummary' => $row->cardsummary,
            'defaultsort' => $row->defaultsort,
            'publicdescription' => $row->publicdescription,
            'publicdescriptionformat' => $row->publicdescriptionformat,
            'image' => ($file !== null && $file->is_valid_image()) ? [
                'contextid' => (int) $file->get_contextid(),
                'contenthash' => $file->get_contenthash(),
                'filename' => $file->get_filename(),
            ] : null,
            'cover' => ($cover !== null && $cover->is_valid_image()) ? [
                'contextid' => (int) $cover->get_contextid(),
                'contenthash' => $cover->get_contenthash(),
                'filename' => $cover->get_filename(),
            ] : null,
            'covervideo' => ($video !== null && $videotype !== null) ? [
                'contextid' => (int) $video->get_contextid(),
                'contenthash' => $video->get_contenthash(),
                'filename' => $video->get_filename(),
                'mimetype' => $videotype,
            ] : null,
            'badges' => $badges,
        ];
    }

    /**
     * Forget the whole cached branding.
     *
     * The whole cache and not one entry, because an entry holds the RESOLVED colour pair: a row
     * saved or deleted changes the pair of every descendant that has none of its own, and a
     * category moved under another parent changes its own pair and every such descendant's. Finding
     * those descendants would cost a statement on a write that happens a few times a year; the
     * entries rebuild on the next read, at seven statements each plus one for a whole grid.
     *
     * @return void
     */
    public static function forget_all(): void {
        \core_cache\cache::make(self::COMPONENT, self::CACHE)->purge();
    }

    /**
     * The file of one single-file area of a category, whatever it is; the caller decides what it is.
     *
     * Each of the editor's file managers takes a single file ({@see single_file_areas()}), so every
     * area this reads holds at most one file. The callers check what it is for themselves: a
     * non-image that reached the hero area another way is neither linked nor served, and one
     * that reached a badge area is named but never served ({@see for_category()} says why the
     * two differ).
     *
     * @param int $categoryid The category.
     * @param string $area The area: {@see AREA_HERO}, {@see AREA_COVER}, {@see AREA_COVERVIDEO}
     *   or one of {@see AREA_BADGES}. The name says image because five of the six hold one; the
     *   method reads a single-file area and asks nothing about the bytes.
     * @return \stored_file|null The file, or null for an empty area or a category with no context.
     */
    public static function image(int $categoryid, string $area = self::AREA_HERO): ?\stored_file {
        $context = context\coursecat::instance($categoryid, IGNORE_MISSING);
        if (!$context) {
            return null;
        }
        $files = get_file_storage()->get_area_files($context->id, self::COMPONENT, $area, 0, 'filename', false);
        $file = reset($files);
        return $file ?: null;
    }

    /**
     * The badge files of a category, in display order, gaps closed.
     *
     * The area name is the position, so an empty second slot closes up rather than leaving a
     * hole: a manager who empties one slot sees the row shorten, not a gap. Keyed by the area
     * because the URL of a badge carries it - a list without it could not be addressed.
     *
     * @param int $categoryid The category.
     * @return \stored_file[] Area name => file, in the order of {@see AREA_BADGES}.
     */
    public static function badges(int $categoryid): array {
        $badges = [];
        foreach (self::AREA_BADGES as $area) {
            $file = self::image($categoryid, $area);
            if ($file !== null) {
                $badges[$area] = $file;
            }
        }
        return $badges;
    }

    /**
     * The mimetype a stored file's own first bytes say it is, or null for anything else.
     *
     * Core has no video validator, which is the reason this exists.
     * {@see \stored_file::is_valid_image()} answers for pictures; for everything else core
     * derives a mimetype from the file name through `mimeinfo()`, so `capa.mp4` holding a text
     * file, a PDF or a same-origin HTML document is `video/mp4` to every core reader of that
     * record - including a browser, which would be handed those bytes under a type the theme
     * chose. So the file is asked what it is: an MP4 carries the four bytes `ftyp` at offset 4
     * (the box type of the ISO base media file format's first box, ISO/IEC 14496-12), and a WebM
     * opens with the EBML magic `1A 45 DF A3` at offset 0.
     *
     * What this does not separate: the EBML magic is Matroska's, not WebM's - a `.mkv` renamed
     * `.webm` passes it, and would then be served as `video/webm` and simply not play. Telling
     * the two apart means parsing the EBML DocType element, which is a parser rather than a
     * check; the editor's file manager accepts two extensions, and the failure this guard exists for
     * is a file that is not a video at all.
     *
     * The bytes are read through the file's own handle, {@see SIGNATURE_BYTES} of them, so a
     * 20 MB video is never pulled into memory to be graded.
     *
     * @param \stored_file $file The stored file.
     * @return string|null One of the values of {@see VIDEO_TYPES}, or null when the bytes are
     *   neither, when the file is shorter than the signature, or when it cannot be opened.
     */
    public static function video_type(\stored_file $file): ?string {
        if ($file->get_filesize() < self::SIGNATURE_BYTES) {
            return null;
        }
        $handle = $file->get_content_file_handle();
        if (!is_resource($handle)) {
            return null;
        }
        $header = (string) fread($handle, self::SIGNATURE_BYTES);
        fclose($handle);
        if (strlen($header) < self::SIGNATURE_BYTES) {
            return null;
        }
        if (substr($header, 4, 4) === 'ftyp') {
            return self::VIDEO_TYPES['mp4'];
        }
        if (substr($header, 0, 4) === "\x1a\x45\xdf\xa3") {
            return self::VIDEO_TYPES['webm'];
        }
        return null;
    }

    /**
     * Whether a stored file's own bytes say it is a video this theme serves.
     *
     * The predicate behind {@see video_type()}, for the two callers that need the answer and not
     * the type: the editor's validation, which refuses the upload with a message, and
     * {@see resolve()}, which refuses the request with the same bare 404 a stale hash gets.
     *
     * @param \stored_file $file The stored file.
     * @return bool
     */
    public static function is_valid_video(\stored_file $file): bool {
        return self::video_type($file) !== null;
    }

    /**
     * The URL of a category's hero image through this route.
     *
     * The content hash in the path is the cache-buster: a replaced image has a new address,
     * so no browser, proxy or scraper keeps showing the old one.
     *
     * @param int $categoryid The category.
     * @param array $image The image entry of {@see for_category()}: contextid, contenthash, filename.
     * @return \core\url The URL.
     */
    public static function image_url(int $categoryid, array $image): \core\url {
        return \core\url::make_pluginfile_url(
            (int) $image['contextid'],
            self::COMPONENT,
            self::AREA_HERO,
            $categoryid,
            '/' . $image['contenthash'] . '/',
            $image['filename']
        );
    }

    /**
     * The URL of one badge of a category through this route.
     *
     * The same shape as {@see image_url()} - the category id as the item id, the content hash as
     * the cache-buster - with the area naming which of the three badges it is.
     *
     * @param int $categoryid The category.
     * @param string $area The badge area, one of {@see AREA_BADGES}.
     * @param array $badge One entry of the badges map of {@see for_category()}.
     * @return \core\url The URL.
     */
    public static function badge_url(int $categoryid, string $area, array $badge): \core\url {
        return \core\url::make_pluginfile_url(
            (int) $badge['contextid'],
            self::COMPONENT,
            $area,
            $categoryid,
            '/' . $badge['contenthash'] . '/',
            $badge['filename']
        );
    }

    /**
     * The URL of a category's card cover image through this route.
     *
     * The same shape as {@see image_url()} - the category id as the item id, the content hash as
     * the cache-buster - with the area saying it is the card's still rather than the hero's
     * background.
     *
     * @param int $categoryid The category.
     * @param array $cover The cover entry of {@see for_category()}: contextid, contenthash, filename.
     * @return \core\url The URL.
     */
    public static function cover_url(int $categoryid, array $cover): \core\url {
        return \core\url::make_pluginfile_url(
            (int) $cover['contextid'],
            self::COMPONENT,
            self::AREA_COVER,
            $categoryid,
            '/' . $cover['contenthash'] . '/',
            $cover['filename']
        );
    }

    /**
     * The URL of a category's card cover video through this route.
     *
     * @param int $categoryid The category.
     * @param array $video The covervideo entry of {@see for_category()}: contextid, contenthash,
     *   filename and mimetype. The mimetype is not in the address - it is what the route sends -
     *   so a replaced file of the other type is a new address either way.
     * @return \core\url The URL.
     */
    public static function covervideo_url(int $categoryid, array $video): \core\url {
        return \core\url::make_pluginfile_url(
            (int) $video['contextid'],
            self::COMPONENT,
            self::AREA_COVERVIDEO,
            $categoryid,
            '/' . $video['contenthash'] . '/',
            $video['filename']
        );
    }

    /**
     * The badges of a category as a surface draws them: the address and the alternative text.
     *
     * The one builder of both category surfaces - the hero and the directory card - so that a
     * crest cannot be addressed one way on one of them and another way on the other. The
     * alternative text is the file name without its extension
     * ({@see publicfiles::badge_alt()}), which is the same rule the landing page's badges follow and
     * the one thing the manager controls: there is no caption field, on purpose.
     *
     * @param int $categoryid The category.
     * @param array $badges The badges map of {@see for_category()}.
     * @return array Entries of ['url' => string, 'alt' => string], in display order, plain spelling.
     */
    public static function badges_for_output(int $categoryid, array $badges): array {
        $entries = [];
        foreach ($badges as $area => $badge) {
            $entries[] = [
                'url' => self::badge_url($categoryid, (string) $area, $badge)->out(false),
                'alt' => publicfiles::badge_alt((string) $badge['filename']),
            ];
        }
        return $entries;
    }

    /**
     * The URL of the site-wide fallback image, absolute, or null when the setting holds none.
     *
     * The setting's own file area in the system context, addressed the way core addresses a
     * theme setting file: the theme revision in the path, so a replaced image has a new URL.
     *
     * @return string|null
     */
    public static function fallback_url(): ?string {
        global $CFG;

        // Loaded by name, never through $PAGE->theme: reading that property initialises the
        // page's theme as a side effect, which a caller setting the page up cannot undo.
        $theme = \core\output\theme_config::load('dimensions');
        $url = $theme->setting_file_url(self::SETTING_FALLBACK, self::AREA_FALLBACK);
        if ($url === null) {
            return null;
        }
        // Core hands the address back protocol-relative ("//host/..."), a spelling core\url
        // cannot hold: it would prefix wwwroot and double the host. The scheme is the site's own.
        if (str_starts_with($url, '//')) {
            $url = parse_url($CFG->wwwroot, PHP_URL_SCHEME) . ':' . $url;
        }
        return $url;
    }

    /**
     * The site-wide fallback image as a stored file, or null when the setting holds none.
     *
     * {@see fallback_url()} answers the same question for a page, through the theme's own
     * setting route; this answers it for the file itself, which is what the Open Graph
     * derivative is made from.
     *
     * @return \stored_file|null The first file of the area that is an image, or null.
     */
    public static function fallback_file(): ?\stored_file {
        $context = context\system::instance();
        $files = get_file_storage()->get_area_files(
            $context->id,
            self::COMPONENT,
            self::AREA_FALLBACK,
            0,
            'filename',
            false
        );
        foreach ($files as $file) {
            if ($file->is_valid_image()) {
                return $file;
            }
        }
        return null;
    }

    /**
     * The image a category's Open Graph derivative is made from: its own, else the site-wide one.
     *
     * The fallback applies whatever the hero's layout is. The hero only paints a photograph
     * under the image layout, but a link preview has nothing else to show, so a category on the
     * solid band still unfurls with the institution's picture rather than with none.
     *
     * @param int $categoryid The category.
     * @return \stored_file|null The source image, or null when there is none anywhere.
     */
    public static function og_source(int $categoryid): ?\stored_file {
        $file = self::image($categoryid);
        if ($file !== null && $file->is_valid_image()) {
            return $file;
        }
        return self::fallback_file();
    }

    /**
     * The URL of a category's Open Graph derivative through this route.
     *
     * The category's own context, even when the source is the site-wide fallback: the route
     * resolves by the category id in the path, and the content hash is the cache-buster, so a
     * replaced image - the category's or the site's - has a new address.
     *
     * @param int $categoryid The category.
     * @param \stored_file $source The source image ({@see og_source()}).
     * @return \core\url The URL.
     */
    public static function ogimage_url(int $categoryid, \stored_file $source): \core\url {
        return \core\url::make_pluginfile_url(
            context\coursecat::instance($categoryid)->id,
            self::COMPONENT,
            self::AREA_OGIMAGE,
            $categoryid,
            '/' . $source->get_contenthash() . '/',
            self::OG_FILENAME
        );
    }

    /**
     * The Open Graph image of a category's public page, if it has one.
     *
     * The derivative is made here, once per content, so that the tags only claim a size the
     * route will serve; a source no derivative can be made from yields nothing at all, because
     * an image tag pointing at nothing usable is worse than none.
     *
     * @param int $categoryid The category.
     * @return array|null Keys url, type, width and height, or null.
     */
    public static function ogimage(int $categoryid): ?array {
        $source = self::og_source($categoryid);
        if ($source === null || publicfiles::derivative($source) === null) {
            return null;
        }
        return [
            'url' => self::ogimage_url($categoryid, $source)->out(false),
            'type' => publicfiles::OG_TYPE,
            'width' => publicfiles::OG_WIDTH,
            'height' => publicfiles::OG_HEIGHT,
        ];
    }

    /**
     * The one way a file may reach any of the six file fields of the editor: as a copy.
     *
     * A file manager's default return types are a copy, an alias and a controlled link
     * (lib/form/filemanager.php). An alias is a file whose bytes live in somebody else's
     * repository: delete the source and the route is left holding a stored_file it cannot read.
     * Naming FILE_INTERNAL alone does two things in core - the picker offers no "create an alias"
     * choice, and file_save_draft_area_files() skips an external file it finds in the draft
     * (lib/filelib.php, the `$allowreferences` branch), so one that arrives some other way never
     * reaches the area either.
     *
     * The constant is core's and lives in repository/lib.php, which not every path that reads
     * these options has loaded - hence the require here rather than a bare constant in an array.
     *
     * @return int FILE_INTERNAL.
     */
    public static function copy_only(): int {
        global $CFG;
        require_once($CFG->dirroot . '/repository/lib.php');
        return FILE_INTERNAL;
    }

    /**
     * The file manager options of the hero image: one web image, no folders, a copy only.
     *
     * @return array
     */
    public static function filemanager_options(): array {
        return [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['web_image'],
            'return_types' => self::copy_only(),
        ];
    }

    /**
     * The file manager options of one badge: one file, the landing page's own limit and types.
     *
     * The limit and the vocabulary are the landing page's, read from its own constant rather than
     * restated: the two surfaces show the same kind of file, an institutional crest, and a
     * category manager who has one accepted on a course must not be refused it here. The
     * landing page's badge fields (customfield_picture's type, where that optional plugin is installed)
     * take exactly these two values
     * ({@see \theme_dimensions\local\landingfields::CREST_MAXBYTES} and
     * accepted_types web_image), which on 5.2 admits .svg - see {@see resolve()} for why an SVG
     * crest is still served.
     *
     * @return array The options a filemanager element takes.
     */
    public static function badge_options(): array {
        return [
            'subdirs' => 0,
            'maxfiles' => 1,
            'maxbytes' => landingfields::CREST_MAXBYTES,
            'accepted_types' => ['web_image'],
            'return_types' => self::copy_only(),
        ];
    }

    /**
     * The file manager options of the card cover: one web image, the hero's own vocabulary.
     *
     * Byte for byte the hero's own option set ({@see filemanager_options()}), because it is the
     * same kind of file in a different frame - including the absence of a maxbytes, which leaves
     * core's own -1 on the element and the site's upload limit in force, exactly as for the hero
     * image.
     *
     * @return array The options a filemanager element takes.
     */
    public static function cover_options(): array {
        return self::filemanager_options();
    }

    /**
     * How large a cover video may be, from the site setting, with {@see DEFAULT_COVERVIDEO_MAXBYTES} behind it.
     *
     * An unwritten setting (no administrator has saved the tab yet) and an empty value both mean
     * "nobody chose" and take the default. A stored zero would mean an unusable field, which is
     * not a choice the select offers.
     *
     * @return int The limit in bytes.
     */
    public static function covervideo_maxbytes(): int {
        $stored = (int) get_config(self::COMPONENT, self::SETTING_COVERVIDEO_MAXBYTES);
        return $stored > 0 ? $stored : self::DEFAULT_COVERVIDEO_MAXBYTES;
    }

    /**
     * The file manager options of the card cover video: one file, the two extensions, the setting's limit.
     *
     * accepted_types is the extensions of {@see VIDEO_TYPES} and not a core type group: the group
     * `video` admits everything core knows how to name, .avi and .mov among them, which a browser
     * will not play inline. The list here is what the `<video>` element can be asked to decode.
     * It is a name check and nothing more - {@see is_valid_video()} is what reads the bytes, and
     * the form's validation is where a manager is told.
     *
     * @return array The options a filemanager element takes.
     */
    public static function covervideo_options(): array {
        $extensions = [];
        foreach (array_keys(self::VIDEO_TYPES) as $extension) {
            $extensions[] = '.' . $extension;
        }
        return [
            'subdirs' => 0,
            'maxfiles' => 1,
            'maxbytes' => self::covervideo_maxbytes(),
            'accepted_types' => $extensions,
            'return_types' => self::copy_only(),
        ];
    }

    /**
     * The five single-file areas of the editor, each with the options it is built, prepared and saved with.
     *
     * One list for the form, {@see form_data()} and {@see save()}, because of the limit: an
     * element offered with one option set, prepared with a second and written back with a third is
     * how a size bound stops applying at exactly the moment it is enforced. With one list the
     * element, the draft area and the stored area cannot differ in their bounds any more than
     * they can in their name.
     *
     * The order is the order of the editor's rows: the card's still, its film, then the three
     * crests in badge order. The hero image is not here - its element carries a name of its own
     * (`heroimage`) rather than its area's, which is what {@see save()}'s own draft argument is
     * for.
     *
     * @return array Area name => the options a filemanager element and file_save_draft_area_files take.
     */
    public static function single_file_areas(): array {
        $areas = [
            self::AREA_COVER => self::cover_options(),
            self::AREA_COVERVIDEO => self::covervideo_options(),
        ];
        foreach (self::AREA_BADGES as $area) {
            $areas[$area] = self::badge_options();
        }
        return $areas;
    }

    /**
     * The editor's initial data: the row's choices, the public description as an editor value, the draft area.
     *
     * @param int $categoryid The category.
     * @return \stdClass What the form's set_data() takes.
     */
    public static function form_data(int $categoryid): \stdClass {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $context = context\coursecat::instance($categoryid);
        $row = self::record($categoryid);
        $draftitemid = file_get_submitted_draft_itemid('heroimage');
        file_prepare_draft_area(
            $draftitemid,
            $context->id,
            self::COMPONENT,
            self::AREA_HERO,
            0,
            self::filemanager_options()
        );
        /* One draft area per single-file area, each named after the area it belongs to: the
           editor's five file managers carry those names, and {@see save()} reads them back by the
           same name, so the element, the draft area and the stored area cannot drift apart. */
        $drafts = [];
        foreach (self::single_file_areas() as $area => $options) {
            $draft = file_get_submitted_draft_itemid($area);
            file_prepare_draft_area($draft, $context->id, self::COMPONENT, $area, 0, $options);
            $drafts[$area] = $draft;
        }
        /* Without a pair of its own the two fields open on the pair the category draws - its
           nearest ancestor's or the theme's - so ticking "own colours" starts from what the page
           shows rather than from the stored default nothing reads. */
        $pair = $row->owncolours ? ['bgcolour' => $row->bgcolour, 'inkcolour' => $row->inkcolour]
            : self::colours([$categoryid])[$categoryid];
        return (object) ($drafts + [
            'id' => $categoryid,
            'layout' => $row->layout,
            'owncolours' => (int) $row->owncolours,
            'bgcolour' => $pair['bgcolour'],
            'inkcolour' => $pair['inkcolour'],
            'overlay' => $row->overlay,
            'tagline' => $row->tagline,
            'publicsummary' => $row->publicsummary,
            'cardsummary' => $row->cardsummary,
            'defaultsort' => $row->defaultsort,
            'publicdescription_editor' => [
                'text' => $row->publicdescription,
                'format' => $row->publicdescriptionformat,
            ],
            'heroimage' => $draftitemid,
        ]);
    }

    /**
     * @var string An attribute of the public text naming a file this site can never publish.
     *
     * The public page text is link-only: its editor offers the URL field and no upload path at
     * all, and the text has no file area of its own
     * ({@see \theme_dimensions\form\categoryhero::publictext_editor_options()}).
     * Three address shapes can still reach it, all of them from a browser rather than from a
     * dialogue - an image pasted into the body or dragged onto it - and none of them survives
     * being read by somebody else:
     *
     * - `data:` and `blob:` are not in core's HTMLPurifier scheme list (`purify_html()`), so the
     *   public page drops the element while the stored row keeps whatever was pasted, which for a
     *   photograph is a megabyte of base64 nobody can see.
     * - a `draftfile.php` address is http, so it survives the purifier and breaks later: the file
     *   belongs to one person's draft area, answers everybody else with a refusal, and core's own
     *   cleanup deletes a draft area after four days.
     *
     * So the form refuses the submission on that field and says what to do instead, rather than
     * storing a link certain to break or silently deleting what somebody wrote. The match is
     * anchored on the attribute, so the same letters in prose are not a refusal.
     */
    public const UNSERVABLE_ADDRESS = '~\b(?:src|srcset|href|poster)\s*=\s*["\']?\s*(?:data:|blob:|[^"\'\s>]*/draftfile\.php/)~i';

    /**
     * Whether a submitted rich text names a file this site could never publish.
     *
     * @param string $html The submitted text.
     * @return bool True when at least one address of {@see UNSERVABLE_ADDRESS} is in it.
     */
    public static function holds_unservable_address(string $html): bool {
        return preg_match(self::UNSERVABLE_ADDRESS, $html) === 1;
    }

    /**
     * The column values of a submission, each checked against its vocabulary.
     *
     * The form validates the same things first and reports them to the person; this is the
     * guard for every other caller, and it throws rather than storing a value the hero could
     * not draw.
     *
     * Whether the pair is the category's own is owncolours when the submission names it (the form
     * always does, through an advanced checkbox), and otherwise whether the submission names a
     * colour at all: a caller handing over a pair means that pair. Without a pair of its own the
     * colours submitted are ignored, unvalidated, and the default pair is stored in their place -
     * nothing reads it, and the category draws the pair it inherits ({@see colours()}).
     *
     * @param \stdClass $data The submission: layout, owncolours, bgcolour, inkcolour, overlay,
     *   tagline, publicsummary, cardsummary, defaultsort, and either publicdescription_editor (an
     *   editor value) or publicdescription + publicdescriptionformat.
     * @return array The column values.
     * @throws \invalid_parameter_exception For a value outside its vocabulary, a colour of its own
     *   that is not a hex value, a pair of its own under {@see FLOOR}, a tagline, a public
     *   description or a card text too long, or a public page text naming an address of
     *   {@see UNSERVABLE_ADDRESS}.
     */
    public static function validate(\stdClass $data): array {
        $layout = (string) ($data->layout ?? self::DEFAULT_LAYOUT);
        if (!in_array($layout, self::LAYOUTS, true)) {
            throw new \invalid_parameter_exception('Unknown hero layout: ' . $layout);
        }
        $owncolours = property_exists($data, 'owncolours')
            ? !empty($data->owncolours)
            : (isset($data->bgcolour) || isset($data->inkcolour));
        $bgcolour = self::DEFAULT_BG;
        $inkcolour = self::DEFAULT_INK;
        if ($owncolours) {
            $bgcolour = self::hex((string) ($data->bgcolour ?? self::DEFAULT_BG));
            if ($bgcolour === null) {
                throw new \invalid_parameter_exception('The hero background colour is not a hex colour.');
            }
            $inkcolour = self::hex((string) ($data->inkcolour ?? self::DEFAULT_INK));
            if ($inkcolour === null) {
                throw new \invalid_parameter_exception('The hero text colour is not a hex colour.');
            }
            $ratio = self::contrast($bgcolour, $inkcolour);
            if ($ratio < self::FLOOR) {
                throw new \invalid_parameter_exception(
                    'The hero colours read ' . self::format_ratio($ratio) . ':1, under the ' . self::FLOOR . ':1 minimum.'
                );
            }
        }
        $submitted = $data->overlay ?? self::DEFAULT_OVERLAY;
        $overlay = self::overlay($submitted);
        if ($overlay === null) {
            throw new \invalid_parameter_exception('Unknown hero overlay: ' . (is_scalar($submitted) ? $submitted : ''));
        }
        $tagline = trim((string) ($data->tagline ?? ''));
        if (\core_text::strlen($tagline) > self::TAGLINE_MAXLENGTH) {
            throw new \invalid_parameter_exception('The tagline is longer than ' . self::TAGLINE_MAXLENGTH . ' characters.');
        }
        /* Trimmed before it is measured and before it is stored: a value of nothing but spaces
           is no description at all, and og:description is omitted for it rather than carrying
           one space - which is what a scraper would then show under the link. */
        $publicsummary = trim((string) ($data->publicsummary ?? ''));
        if (\core_text::strlen($publicsummary) > self::PUBLICSUMMARY_MAXLENGTH) {
            throw new \invalid_parameter_exception(
                'The public description is longer than ' . self::PUBLICSUMMARY_MAXLENGTH . ' characters.'
            );
        }
        /* Plain text, trimmed before it is measured for the reason the public description is: a
           value of spaces alone is no card text, and the card falls back to the description. */
        $cardsummary = trim((string) ($data->cardsummary ?? ''));
        if (\core_text::strlen($cardsummary) > self::CARDSUMMARY_MAXLENGTH) {
            throw new \invalid_parameter_exception(
                'The card text is longer than ' . self::CARDSUMMARY_MAXLENGTH . ' characters.'
            );
        }
        $defaultsort = self::sort_choice((string) ($data->defaultsort ?? ''));
        if ($defaultsort === null) {
            throw new \invalid_parameter_exception('Unknown default organisation.');
        }
        if (isset($data->publicdescription_editor) && is_array($data->publicdescription_editor)) {
            $text = (string) ($data->publicdescription_editor['text'] ?? '');
            $format = (int) ($data->publicdescription_editor['format'] ?? FORMAT_HTML);
        } else {
            $text = (string) ($data->publicdescription ?? '');
            $format = (int) ($data->publicdescriptionformat ?? FORMAT_HTML);
        }
        if (self::holds_unservable_address($text)) {
            throw new \invalid_parameter_exception('The public page text names a file this site cannot publish.');
        }
        return [
            'layout' => $layout,
            'bgcolour' => $bgcolour,
            'inkcolour' => $inkcolour,
            'owncolours' => $owncolours ? 1 : 0,
            'overlay' => $overlay,
            'tagline' => $tagline,
            'publicsummary' => $publicsummary,
            'cardsummary' => $cardsummary,
            'defaultsort' => $defaultsort,
            'publicdescription' => $text,
            'publicdescriptionformat' => $format,
        ];
    }

    /**
     * Store a submission: the row, the image, the whole cache dropped ({@see forget_all()}), the event.
     *
     * @param int $categoryid The category.
     * @param \stdClass $data The submission, see {@see validate()}.
     * @param int $draftitemid The draft area holding the hero image, 0 to leave the area alone.
     * @return \stdClass The stored row, read back.
     */
    public static function save(int $categoryid, \stdClass $data, int $draftitemid = 0): \stdClass {
        global $CFG, $DB, $USER;

        $context = context\coursecat::instance($categoryid);
        $values = self::validate($data);
        $now = time();
        $existing = $DB->get_record(self::TABLE, ['categoryid' => $categoryid]);
        if ($existing) {
            $record = (object) ($values + [
                'id' => (int) $existing->id,
                'usermodified' => (int) $USER->id,
                'timemodified' => $now,
            ]);
            $DB->update_record(self::TABLE, $record);
        } else {
            $record = (object) ($values + [
                'categoryid' => $categoryid,
                'usermodified' => (int) $USER->id,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $record->id = (int) $DB->insert_record(self::TABLE, $record);
        }
        if ($draftitemid) {
            require_once($CFG->libdir . '/filelib.php');
            file_save_draft_area_files(
                $draftitemid,
                $context->id,
                self::COMPONENT,
                self::AREA_HERO,
                0,
                self::filemanager_options()
            );
        }
        /* The five single-file areas, each read off the submission under the name of its own area
           and written back with the very options it was prepared with.

           A draft id of zero and an empty draft area are two different things, and keeping them
           apart is what makes a file removable at all. Zero is "no element was submitted" - a
           caller that never offered the row, as every test of the row above is - and the area is
           left exactly as it was, because to core a zero draft area is "no files" and the call
           would empty the target area. A real draft id holding nothing is the manager having
           taken the file away in the editor, and emptying the area is the whole point of the
           call. */
        foreach (self::single_file_areas() as $area => $options) {
            $draft = (int) ($data->$area ?? 0);
            if (!$draft) {
                continue;
            }
            require_once($CFG->libdir . '/filelib.php');
            file_save_draft_area_files($draft, $context->id, self::COMPONENT, $area, 0, $options);
        }
        self::forget_all();
        category_branding_updated::create(['objectid' => $record->id, 'context' => $context])->trigger();
        return self::record($categoryid);
    }

    /**
     * Drop a category's row and the cached branding, every descendant's resolved pair included.
     *
     * The files of the branding's areas are not touched here: on deletion core deletes the
     * category's context and every file in it before the event fires (branding_test asserts it
     * for the hero and badge areas), and outside deletion an area is the editor's to empty.
     * Deleting them here would be code no test could tell from its absence.
     *
     * @param int $categoryid The category.
     * @return void
     */
    public static function delete_for_category(int $categoryid): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['categoryid' => $categoryid]);
        self::forget_all();
    }

    /**
     * Observer of core's course_category_deleted: the branding row goes with the category.
     *
     * @param \core\event\course_category_deleted $event The event.
     * @return void
     */
    public static function category_deleted(\core\event\course_category_deleted $event): void {
        self::delete_for_category((int) $event->objectid);
    }

    /**
     * Observer of core's course_category_updated: a category may have moved, so every resolved pair may have.
     *
     * Core fires this event from update(), change_parent() (a move), hide(), show() and, for each
     * child it moves, delete_move(). The event does not say which of them fired it, and a move
     * changes the pair of the category and of every descendant without one of its own, so the
     * whole cache goes; an edit of a name or a visibility costs the same rebuild, which is cheap
     * for a write this rare.
     *
     * @param \core\event\course_category_updated $event The event.
     * @return void
     */
    public static function category_updated(\core\event\course_category_updated $event): void {
        self::forget_all();
    }

    /**
     * Whether the current viewer may list the category: core's question first, then the plugin's.
     *
     * The same two questions the page asks before rendering the showcase, in the same order:
     * the plugin is a layer over core visibility and answers "yes" wholesale for a hidden
     * category. A visitor under forcelogin holds no capability, so core refuses them here as
     * it refuses them the page.
     *
     * @param int $categoryid The category.
     * @return bool
     */
    public static function viewer_may_see(int $categoryid): bool {
        $coursecat = core_course_category::get($categoryid, IGNORE_MISSING, true);
        if ($coursecat === null || !$coursecat->is_uservisible()) {
            return false;
        }
        return (bool) category_access::filter_categories([$coursecat]);
    }

    /**
     * Resolve a request of the image route to the stored file it names, or refuse it.
     *
     * Nothing about the request is trusted: the context must be a category's, the category id
     * in the path must be that context's own instance (the file is looked up by the id, so a
     * mismatch would pair one category's context with another's image), the category must be
     * one the requester may see now - the page that minted the URL is not trusted - the path
     * must name the current content and its own file name, and only an image (or, in the cover
     * video area, a video by its signature) is served: a same-origin .html from a file area
     * would be script for everyone who can reach it.
     *
     * "May see now" is a different question for each class of requester, and the branch is the
     * whole public half of this route: a visitor - nobody logged in at all - is asked
     * {@see publicaccess::is_public()}, the anonymous shell's own gate, which fails closed and
     * answers before any lookup, and failing that the two questions of the logged-in page, so
     * that a site running without forcelogin - where a visitor may browse a listed category on
     * core's own page - still gets its hero; anybody else, the guest account included, is
     * asked those two questions alone. The split is the page's own: the guest lands on the
     * logged-in shell, so its image follows that shell's answer, and the route is never wider
     * than the page that draws it.
     *
     * @param context $context The context the URL names.
     * @param array $args The path segments after the area: the category id, the content hash, the file name.
     * @param string $filearea The area: {@see AREA_HERO}, {@see AREA_COVER}, {@see AREA_COVERVIDEO},
     *   one of {@see AREA_BADGES}, or {@see AREA_OGIMAGE} for the Open Graph derivative.
     * @return \stored_file|null The file to send, or null to refuse.
     */
    public static function resolve(context $context, array $args, string $filearea = self::AREA_HERO): ?\stored_file {
        if ($context->contextlevel != CONTEXT_COURSECAT) {
            return null;
        }
        $categoryid = (int) array_shift($args);
        if ($categoryid !== (int) $context->instanceid) {
            return null;
        }
        $visitor = !isloggedin();
        $admitted = $visitor
            ? (publicaccess::is_public($categoryid) || self::viewer_may_see($categoryid))
            : self::viewer_may_see($categoryid);
        if (!$admitted) {
            return null;
        }
        if (count($args) !== 2) {
            return null;
        }
        [$hash, $filename] = $args;
        if ($filearea === self::AREA_HERO) {
            $file = self::image($categoryid);
            if ($file === null || $file->get_contenthash() !== $hash || $file->get_filename() !== $filename) {
                return null;
            }
            if (!$file->is_valid_image()) {
                return null;
            }
            return $file;
        }
        if ($filearea === self::AREA_COVER) {
            /* The card's still, on exactly the hero's checks above: the same admission, the same
               current-content pairing, and the same image question - a file area that serves
               whatever reached it is same-origin script for everyone who may see the category. */
            $cover = self::image($categoryid, self::AREA_COVER);
            if ($cover === null || $cover->get_contenthash() !== $hash || $cover->get_filename() !== $filename) {
                return null;
            }
            if (!$cover->is_valid_image()) {
                return null;
            }
            return $cover;
        }
        if ($filearea === self::AREA_COVERVIDEO) {
            /* The video, on the same checks with the signature in place of the image question.
               A file whose first bytes are neither an MP4 ftyp box nor an EBML header is refused
               with the bare 404 a stale hash gets - and it is also absent from the cached payload
               ({@see for_category()}), so nothing on a page ever asks for it. The two together
               make "a video is never trusted by its name" true of the route and not only of the
               editor: anything writing through the file API bypasses the editor, and this is the
               sink. */
            $video = self::image($categoryid, self::AREA_COVERVIDEO);
            if ($video === null || $video->get_contenthash() !== $hash || $video->get_filename() !== $filename) {
                return null;
            }
            if (!self::is_valid_video($video)) {
                return null;
            }
            return $video;
        }
        if (in_array($filearea, self::AREA_BADGES, true)) {
            /* Exactly the hero's checks, is_valid_image() included, as the landing page's own badge
               route makes ({@see \theme_dimensions\local\landing\publicfiles::resolve()}):
               without it a same-origin .html written through the file API would be script for
               everyone who may see the category; the editor's file managers accept images only,
               and this check makes that true of the route as well. It costs no crest: core 5.2's
               file_system::get_imageinfo_from_path() parses an SVG for its viewBox rather than
               calling getimagesize(), so a well-formed SVG passes (branding_test asserts an SVG
               served and a non-image refused). Core still sends an SVG with Content-Disposition:
               attachment (send_file(), unless the caller passes dontforcesvgdownload, which
               nothing here does). */
            $badge = self::image($categoryid, $filearea);
            if ($badge === null || $badge->get_contenthash() !== $hash || $badge->get_filename() !== $filename) {
                return null;
            }
            if (!$badge->is_valid_image()) {
                return null;
            }
            return $badge;
        }
        if ($filearea === self::AREA_OGIMAGE) {
            $source = self::og_source($categoryid);
            if ($source === null || $source->get_contenthash() !== $hash || $filename !== self::OG_FILENAME) {
                return null;
            }
            if (!$source->is_valid_image()) {
                return null;
            }
            return $source;
        }
        return null;
    }

    /**
     * Send the image a request names, or answer 404. Does not return.
     *
     * Cacheability is private whenever a logged-in requester's own rights decided the answer,
     * and public for a visitor: that answer depends on the category's stored state alone, and
     * the URL carries the content hash, so a shared cache holding it cannot serve one person
     * something another person's rights withheld. A refusal is a bare 404, never
     * send_file_not_found(), which throws and renders a full themed error page.
     *
     * The og area sends the derivative or nothing: its URL and the tags that carry it promise
     * a 1200x630 JPEG, and the original under that name would be a different file, type and
     * size from what was advertised.
     *
     * The cover video is sent with the type its own bytes say it is, not with the one core
     * derived from its name. send_stored_file() has no way to override that - it reads
     * get_mimetype(), which is mimeinfo() over the extension - so this branch calls send_file()
     * with the mimetype {@see video_type()} matched. A WebM a manager named .mp4 is then served
     * as video/webm and plays, instead of being offered to a browser under a type it is not.
     *
     * Production should enable X-Sendfile for this area above all the others. Core hands the
     * path to the web server when $CFG->xsendfile is set (readfile_accel() inside send_file()),
     * which releases the PHP worker before the bytes are on the wire; without it a 20 MB cover
     * video holds one worker for the whole of a hover, and a grid of cards can hold as many
     * workers as it has cards. Byte ranges are core's and the web server's either way - nothing
     * here parses a Range header - so a browser seeking inside the loop is answered by whichever
     * of them is serving.
     *
     * @param context $context The context the URL names.
     * @param string $filearea The area.
     * @param array $args The path segments after the area.
     * @return void
     */
    public static function serve(context $context, string $filearea, array $args): void {
        $file = self::resolve($context, $args, $filearea);
        if ($file === null) {
            self::refuse();
        }
        \core\session\manager::write_close();
        $visitor = !isloggedin();
        $options = ['cacheability' => $visitor ? 'public' : 'private'];
        if ($filearea === self::AREA_OGIMAGE) {
            $path = publicfiles::derivative($file);
            if ($path === null) {
                self::refuse();
            }
            send_file($path, self::OG_FILENAME, self::HASHED_LIFETIME, 0, false, false, publicfiles::OG_TYPE, false, $options);
        }
        if ($filearea === self::AREA_COVERVIDEO) {
            $type = (string) self::video_type($file);
            send_file($file, $file->get_filename(), self::HASHED_LIFETIME, 0, false, false, $type, false, $options);
        }
        send_stored_file($file, self::HASHED_LIFETIME, 0, false, $options);
    }

    /**
     * Refuse with a bare 404 and stop.
     *
     * Not send_file_not_found(): that throws, and the handler renders a full themed error page
     * through core_renderer::header() - for a visitor of the public page, the render this whole
     * design avoids.
     *
     * @return void
     */
    private static function refuse(): void {
        send_header_404();
        die;
    }
}
