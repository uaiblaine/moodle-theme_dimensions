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
 * Theme Dimensions - The guard over a url printed inside url('...') in a style attribute
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

/**
 * The one sanitiser for a url this theme prints inside url('...') in an inline style.
 *
 * Its callers are the landing page hero's background-image
 * ({@see \theme_dimensions\output\landing_page::hero_image_url()}) and the crest watermark
 * of a category poster ({@see category\population::mark()}), each printed through a DOUBLE stash
 * inside a style attribute of the theme's own template. One class serves them both so that the
 * rule exists once.
 *
 * The stash decides nothing: a browser HTML-decodes an attribute value BEFORE the CSS parser reads
 * it, and Mustache's escaping is an HTML escaping, never a CSS one. Through a triple stash a url
 * carrying `&apos;` or `&rpar;` would reach the CSS parser as a quote and a parenthesis. Through the
 * double stash, s() escapes a literal quote to `&#039;`, which the browser decodes straight back,
 * and deliberately does not re-escape a numeric reference already in the value (the preg_replace
 * at the end of s() in lib/weblib.php), so `&#39;` written as text also arrives as a quote. A url
 * such as `https://example.com/a');background:red;x.png`, which clean_param(PARAM_URL) hands back
 * unchanged, would therefore inject a declaration into the style attribute.
 *
 * So the url is REFUSED rather than repaired, on a vocabulary rather than on a decoded copy:
 * anything clean_param() does not hand back unchanged, and any url carrying a quote, a parenthesis,
 * a backslash, whitespace or an ampersand. The ampersand begins every character reference, in
 * either spelling, so refusing it closes that whole class without depending on PHP's entity table
 * and a browser's agreeing.
 *
 * The cost is that a url with a second query parameter is refused too, and a refusal means no
 * image at all (the landing page hero shows the navy, the poster's watermark the theme's folder).
 * Today that costs nothing: every wired source builds through core's pluginfile url factory,
 * which rawurlencode()s each path segment
 * (core\url::set_slashargument()), so a file name holding a quote, a parenthesis, a space or an
 * ampersand arrives here as %27, %28, %20 or %26 and passes; the factory adds a query parameter
 * only for forcedownload, and core's course_summary_exporter::get_course_image() goes through the
 * same factory. styleurl_test pins that with an encoded file name.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class styleurl {
    /**
     * @var string The characters no url may carry into url('...'), as a preg pattern.
     *
     * A quote closes the url and a second one could close the attribute, a parenthesis closes the
     * function, a backslash escapes whatever follows it, whitespace starts a second value, and the
     * ampersand is every character reference's first character - see the class docblock for what
     * is being closed and why the last one is not obvious.
     */
    private const REFUSED = '~[\s\'"()&\\\\]~';

    /**
     * The url when it may stand inside url('...') in an inline style, '' otherwise.
     *
     * Pure and free of the request on purpose, so that every case can be tested without a page, a
     * course or a renderer: the callers resolve a url and this answers whether it may be printed.
     *
     * @param string $url The resolved url, or '' when there is none.
     * @return string The same url when it is safe to print, '' otherwise.
     */
    public static function safe(string $url): string {
        if ($url === '') {
            return '';
        }

        /* PARAM_URL either hands the value back or answers '' - it repairs nothing - so the second
           comparison is what says so out loud rather than a second cleaning step. */
        $clean = clean_param($url, PARAM_URL);
        if ($clean === '' || $clean !== $url) {
            return '';
        }

        if (preg_match(self::REFUSED, $clean)) {
            return '';
        }

        return $clean;
    }
}
