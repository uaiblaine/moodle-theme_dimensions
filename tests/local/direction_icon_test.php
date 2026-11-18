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
 * A filled triangle reads as "play" or as a sort mark, so it is kept for what plays or resumes something: the cover
 * film's button, and the tile of the drawer's card that resumes the last activity (D106). Everything else that points
 * somewhere draws Font Awesome's arrow or chevron.
 */
#[CoversNothing]
final class direction_icon_test extends \advanced_testcase {
    /** @var array The directories whose sources name an icon, relative to the theme. */
    private const SOURCES = ['classes', 'templates', 'amd/src', 'js/esm/src'];

    /**
     * @var array The files that draw the play triangle, path relative to the theme: the cover film, whose button plays a
     *      video, and the drawer's "Continue where you left off" card, whose tile resumes the activity last opened
     *      (the Design System's D106).
     */
    private const PLAYERS = [
        'templates/category_directory_card.mustache',
        'amd/src/local/coverfilm.js',
        'templates/drawer_nav.mustache',
    ];

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
     * The play triangle is drawn by the cover film and the drawer's resume card alone, and both do draw it.
     */
    public function test_only_the_players_draw_the_play_triangle(): void {
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
