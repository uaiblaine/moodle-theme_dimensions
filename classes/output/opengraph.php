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
 * Theme Dimensions - Open Graph tags of the public pages
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use theme_dimensions\local\category\branding;
use theme_dimensions\local\landing\publicfiles;

/**
 * The head tags of a public page: Open Graph, canonical link and robots directive.
 *
 * Three pages carry them, and all three are anonymous surfaces of this theme: a course's landing page
 * ({@see for_course()}), a category's public page ({@see for_category()}) and the root of the
 * course index ({@see for_root()}). One class and one template for all of them, because a social
 * scraper reads the same tags either way; what differs is only where each value is read from -
 * the category's are in its branding, and the root's are the site's own, it having none.
 *
 * Built by the page's request (\theme_dimensions\local\landing\request and
 * \theme_dimensions\local\category\request) before output starts, registered with
 * {@see set()}, and rendered into the document head by the before_standard_head_html_generation
 * hook callback. Every value is held in the plain spelling and the template's double stashes
 * escape it exactly once for the attribute; the test fixture is a course name carrying a bare
 * ampersand.
 *
 * The description is the course's reviewed public description, never its
 * summary: summaries are written for an internal audience, so a course
 * without a public description gets no og:description at all. The image comes
 * through the theme's own file route ({@see publicfiles::ogimage()}), the only
 * route a visitor can fetch under forcelogin; a course without a usable image
 * gets no og:image. The hook marks the page noindex unless the site is open to
 * search engines ($CFG->opentowebcrawlers): the page exists so that a shared
 * link unfurls, not so that a search engine lists it.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class opengraph implements \core\output\named_templatable, \core\output\renderable {
    /**
     * @var int How many characters of a rich text {@see for_root()} offers as og:description.
     *
     * The same number the reviewed fields are capped at, and for the same reason: a preview card
     * shows about that much and cuts the rest without saying so.
     */
    private const DESCRIPTION_LENGTH = 200;

    /** @var opengraph|null The tags of the page being rendered, if it is a public page of this theme. */
    private static ?opengraph $current = null;

    /**
     * Constructor.
     *
     * @param string $title The page title, plain spelling.
     * @param string|null $description The public description, plain spelling, or null for none.
     * @param string $url The canonical URL of the page.
     * @param string $sitename The site name, plain spelling.
     * @param string $locale The Open Graph locale, e.g. pt_BR.
     * @param array|null $image The image ({@see publicfiles::ogimage()}): url, type, width, height; or null for none.
     */
    public function __construct(
        /** @var string The page title. */
        private readonly string $title,
        /** @var string|null The public description, or null. */
        private readonly ?string $description,
        /** @var string The canonical URL. */
        private readonly string $url,
        /** @var string The site name. */
        private readonly string $sitename,
        /** @var string The Open Graph locale. */
        private readonly string $locale,
        /** @var array|null The image: url, type, width and height; or null. */
        private readonly ?array $image = null,
    ) {
    }

    /**
     * The tags for a course's landing page.
     *
     * @param \stdClass $course The course record.
     * @param string|null $description The course's public description in the plain spelling, or null.
     * @param \moodle_url $url The landing page's own URL.
     * @return opengraph The tags.
     */
    public static function for_course(\stdClass $course, ?string $description, \moodle_url $url): opengraph {
        global $SITE;

        $context = \core\context\course::instance($course->id);
        $title = format_string($course->fullname, true, ['context' => $context, 'escape' => false]);
        $sitename = format_string($SITE->fullname, true, [
            'context' => \core\context\system::instance(),
            'escape' => false,
        ]);
        $description = trim((string) $description);
        return new opengraph(
            $title,
            ($description === '') ? null : $description,
            $url->out(false),
            $sitename,
            self::locale(),
            publicfiles::ogimage($course)
        );
    }

    /**
     * The tags for a category's public page.
     *
     * The title is the category's name in the plain spelling. The description is the branding's
     * own public description and nothing else - the short field written for exactly this, capped
     * at {@see branding::PUBLICSUMMARY_MAXLENGTH} characters, as {@see for_course()} reads the
     * landing page's reviewed field. A category whose field is empty gets no og:description at all,
     * because an empty tag is worse for a preview than none. The tagline does not feed it: it is
     * written for the reader already on the page, not reviewed for a shared link. The image is
     * the 1200x630 derivative the branding route serves, or nothing.
     *
     * @param \core_course_category $category The category.
     * @param array $branding What {@see branding::for_category()} returned for it.
     * @param \core\url $url The page's own canonical address.
     * @return opengraph The tags.
     */
    public static function for_category(\core_course_category $category, array $branding, \core\url $url): opengraph {
        global $SITE;

        $categoryid = (int) $category->id;
        $title = $category->get_formatted_name(['escape' => false]);
        $sitename = format_string($SITE->fullname, true, [
            'context' => \core\context\system::instance(),
            'escape' => false,
        ]);
        $description = trim((string) ($branding['publicsummary'] ?? ''));
        return new opengraph(
            $title,
            ($description === '') ? null : $description,
            $url->out(false),
            $sitename,
            self::locale(),
            branding::ogimage($categoryid)
        );
    }

    /**
     * The tags for the root of the course index, served anonymously.
     *
     * A sibling of {@see for_category()} and never a branch inside it, because the two share no
     * source: the root has no category row, no category context and no branding of its own, and
     * `top()->get_formatted_name()` is the literal word "Top". Everything here is the site's: its
     * full name as the title, its summary as the description.
     *
     * The description keeps `for_category()`'s rule and not its source: an empty summary gets no
     * og:description at all, never a placeholder. The summary is a rich text written for the
     * front page, so only its first paragraph is offered, cut at {@see DESCRIPTION_LENGTH}
     * characters.
     *
     * No og:image. The root's natural picture is the site-wide fallback, but an advertised
     * og:image must be the 1200x630 derivative and `branding::resolve()` refuses anything that is
     * not a category context, so an image here would need a system-context branch in the
     * branding route.
     *
     * @param \core\url $url The root's own canonical address.
     * @return opengraph The tags.
     */
    public static function for_root(\core\url $url): opengraph {
        global $SITE;

        $context = \core\context\system::instance();
        $sitename = format_string($SITE->fullname, true, ['context' => $context, 'escape' => false]);
        $description = self::first_paragraph(
            (string) ($SITE->summary ?? ''),
            (int) ($SITE->summaryformat ?? FORMAT_HTML),
            $context
        );
        return new opengraph(
            $sitename,
            ($description === '') ? null : $description,
            $url->out(false),
            $sitename,
            self::locale(),
            null
        );
    }

    /**
     * The opening of a rich text as one short plain line.
     *
     * Rendered first, so that a filter, a multilang span and an entity all read as the reader
     * sees them, then reduced to text and cut at the first blank line the reduction leaves - which
     * is what content_to_text() writes between two block elements. A summary that is one long
     * paragraph is cut by length alone.
     *
     * @param string $text The stored text.
     * @param int $format Its format.
     * @param \core\context $context The context to format in.
     * @return string The plain line, '' when there is nothing to say.
     */
    private static function first_paragraph(string $text, int $format, \core\context $context): string {
        if (trim($text) === '') {
            return '';
        }
        $plain = trim(content_to_text(format_text($text, $format, ['context' => $context]), FORMAT_HTML));
        if ($plain === '') {
            return '';
        }
        $parts = preg_split('/\R\s*\R/u', $plain, 2);
        $first = trim((string) ($parts[0] ?? ''));
        return ($first === '') ? '' : shorten_text($first, self::DESCRIPTION_LENGTH);
    }

    /**
     * The Open Graph spelling of the current language.
     *
     * Moodle's language codes are lower case with an underscore (pt_br);
     * Open Graph wants the territory upper-cased (pt_BR). A direct copy is
     * invalid, and a code without a territory is passed through as it is.
     *
     * @param string|null $language A Moodle language code, null for the current language.
     * @return string The locale.
     */
    public static function locale(?string $language = null): string {
        $language = $language ?? current_language();
        if (preg_match('/^([a-z]{2,3})_([a-z]{2})$/', $language, $matches)) {
            return $matches[1] . '_' . strtoupper($matches[2]);
        }
        return $language;
    }

    /**
     * Remember the tags of the page being rendered.
     *
     * @param opengraph|null $tags The tags, or null to forget them.
     * @return void
     */
    public static function set(?opengraph $tags): void {
        self::$current = $tags;
    }

    /**
     * The tags of the page being rendered, if it is a public page of this theme.
     *
     * @return opengraph|null The tags, or null.
     */
    public static function current(): ?opengraph {
        return self::$current;
    }

    /**
     * The canonical URL of the page, plain spelling.
     *
     * @return string The URL.
     */
    public function get_url(): string {
        return $this->url;
    }

    /**
     * The template rendering this renderable.
     *
     * @param \core\output\renderer_base $renderer The renderer.
     * @return string The template name.
     */
    public function get_template_name(\core\output\renderer_base $renderer): string {
        return 'theme_dimensions/opengraph';
    }

    /**
     * Export the template context, plain spellings throughout.
     *
     * @param \core\output\renderer_base $output The renderer.
     * @return \stdClass The template context.
     */
    public function export_for_template(\core\output\renderer_base $output): \stdClass {
        return (object) [
            'title' => $this->title,
            'hasdescription' => $this->description !== null,
            'description' => $this->description,
            'url' => $this->url,
            'sitename' => $this->sitename,
            'locale' => $this->locale,
            'hasimage' => $this->image !== null,
            'imageurl' => $this->image['url'] ?? null,
            'imagetype' => $this->image['type'] ?? null,
            'hasimagesize' => isset($this->image['width'], $this->image['height']),
            'imagewidth' => $this->image['width'] ?? null,
            'imageheight' => $this->image['height'] ?? null,
        ];
    }
}
