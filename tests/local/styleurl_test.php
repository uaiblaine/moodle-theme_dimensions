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
 * Theme Dimensions - Tests for the inline style url guard
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the guard over a url printed inside url('...') in a style attribute.
 *
 * Every refusal test ends with an ordinary url that must still pass: a guard answering '' to
 * everything would pass refusal-only tests while removing every image it stands over.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(styleurl::class)]
final class styleurl_test extends \basic_testcase {
    /** @var string A pluginfile url of the shape the sinks really receive. */
    private const ORDINARY = 'https://www.example.com/moodle/pluginfile.php/31/course/overviewfiles/capa.jpg';

    /**
     * An ordinary url is handed back exactly as it came.
     *
     * @return void
     */
    public function test_an_ordinary_url_is_handed_back_unchanged(): void {
        $this->assertSame(self::ORDINARY, styleurl::safe(self::ORDINARY));
    }

    /**
     * A character the CSS parser would act on is refused, whichever sink it aimed at.
     *
     * A quote closes the url and a second one could close the attribute, a parenthesis closes the
     * function, a backslash escapes what follows it, and whitespace starts a second value. The
     * last three entries are not web addresses; they are here so the vocabulary and the cleaning are
     * known to be tested together rather than assumed to overlap.
     *
     * @return void
     */
    public function test_a_character_the_css_parser_would_act_on_is_refused(): void {
        $refused = [
            'a quote closes the url' => "https://www.example.com/a'.png",
            'a double quote closes the attribute' => 'https://www.example.com/a".png',
            'a parenthesis closes the function' => 'https://www.example.com/a).png',
            'an opening parenthesis starts another' => 'https://www.example.com/a(.png',
            'a backslash escapes what follows it' => 'https://www.example.com/a\\.png',
            'whitespace starts a second value' => 'https://www.example.com/a b.png',
            'a newline is whitespace too' => "https://www.example.com/a\n.png",
            'a whole declaration smuggled behind a quote' => "https://www.example.com/a');background:red;x.png",
            'not a url at all' => 'url(javascript:alert(1))',
            'a script scheme' => 'javascript:alert(1)',
            'nothing' => '',
        ];
        foreach ($refused as $why => $url) {
            $this->assertSame('', styleurl::safe($url), $why);
        }

        $this->assertSame(
            self::ORDINARY,
            styleurl::safe(self::ORDINARY),
            'Control: the guard still answers a url, so the refusals above are refusals.'
        );
    }

    /**
     * A character reference is refused too, because the browser decodes the attribute first.
     *
     * {@see styleurl} explains how each spelling reaches the CSS parser and why the refusal is on
     * the ampersand itself. The second assertion of the loop proves the refusal is this class's:
     * PARAM_URL hands every one of these back unchanged.
     *
     * @return void
     */
    public function test_a_character_reference_is_refused_because_the_browser_decodes_the_attribute_first(): void {
        $refused = [
            'a quote and a parenthesis as named references, closing the url and the declaration' =>
                'https://www.example.com/logo.png?rev=7&y=&apos;&rpar;;background:red;/*',
            'a quote alone as a named reference' => 'https://www.example.com/logo.png?a=&apos;',
            'a parenthesis alone as a named reference' => 'https://www.example.com/logo.png?a=&rpar;',
            'a backslash as a named reference' => 'https://www.example.com/logo.png?a=&bsol;',
            'whitespace as a named reference' => 'https://www.example.com/logo.png?a=&Tab;',
            'a quote as a numeric reference, which s() restores rather than escaping' =>
                'https://www.example.com/logo.png?a=&#39;',
            'an ampersand at all, which is what every reference starts with' =>
                'https://www.example.com/logo.png?a=1&b=2',
        ];
        foreach ($refused as $why => $url) {
            $this->assertSame('', styleurl::safe($url), $why);
            $this->assertSame($url, clean_param($url, PARAM_URL), $why . ' - clean_param already refused it');
        }

        $this->assertSame(
            self::ORDINARY,
            styleurl::safe(self::ORDINARY),
            'Control: the guard still answers a url, so the refusals above are refusals.'
        );
    }

    /**
     * A file name holding one of those characters costs nobody an image: it arrives encoded.
     *
     * Pins the price of the guard: core's pluginfile url factory rawurlencode()s each path segment
     * (core\url::set_slashargument()), so a course image called "capa (1) & anexo.jpg" reaches the
     * guard as %20, %28, %29 and %26 and must be printed. See {@see styleurl} for the wired sources.
     *
     * @return void
     */
    public function test_a_file_name_holding_a_refused_character_still_gets_its_image(): void {
        $name = rawurlencode('capa (1) & anexo.jpg');
        $this->assertSame('capa%20%281%29%20%26%20anexo.jpg', $name, 'The factory encodes all four.');

        $url = 'https://www.example.com/moodle/pluginfile.php/31/course/overviewfiles/' . $name;
        $this->assertSame($url, styleurl::safe($url));
    }
}
