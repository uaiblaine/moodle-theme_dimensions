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
 * The glyph a control draws to say "go on" or "open", pinned by reading the theme's sources.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * A direction is an arrow or a chevron, never a caret (moodle-dev/docs/design-system/DECISIONS.md, D61).
 *
 * A filled triangle reads as "play" or as a sort mark, so it is kept for the one control that plays something: the
 * cover film's button. Everything else that points somewhere draws Font Awesome's arrow or chevron.
 */
#[CoversNothing]
final class direction_icon_test extends \advanced_testcase {
    /** @var array The directories whose sources name an icon, relative to the theme. */
    private const SOURCES = ['classes', 'templates', 'amd/src', 'js/esm/src'];

    /** @var array The files of the cover film, whose button plays a video: path relative to the theme. */
    private const PLAYERS = ['templates/category_directory_card.mustache', 'amd/src/local/coverfilm.js'];

    /**
     * Every source file of the theme that can name an icon: relative path => contents.
     *
     * @return array
     */
    private static function sources(): array {
        $root = dirname(__DIR__, 2);
        $found = [];
        foreach (self::SOURCES as $dir) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                $found[substr($file->getPathname(), strlen($root) + 1)] = file_get_contents($file->getPathname());
            }
        }
        return $found;
    }

    /**
     * No source draws a caret.
     */
    public function test_no_source_draws_a_caret(): void {
        $sources = self::sources();
        $this->assertGreaterThan(100, count($sources));
        foreach ($sources as $path => $text) {
            $this->assertDoesNotMatchRegularExpression('/\bfa-(caret|sort)-(up|down|left|right)\b/', $text, $path);
        }
    }

    /**
     * The play triangle is drawn by the cover film alone, and the film does draw it.
     */
    public function test_only_the_cover_film_draws_the_play_triangle(): void {
        $drawn = [];
        foreach (self::sources() as $path => $text) {
            if (preg_match('/\bfa-play\b/', $text)) {
                $drawn[] = $path;
            }
        }
        sort($drawn);
        $players = self::PLAYERS;
        sort($players);
        $this->assertSame($players, $drawn);
    }
}
