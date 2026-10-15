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
 * Theme Dimensions - What the course notice of one page says
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\coursenotice;

/**
 * The items of one page's course notice, as data: chips, addresses, notes and bar actions.
 *
 * Built by {@see builder}, drawn by {@see \theme_dimensions\output\course_notice}. Every
 * item carries a KEY, which is what the tests and the template's data-key attributes name, so a
 * test asserts "the hidden-course note is there" rather than matching a sentence.
 *
 * Two spellings travel here, on purpose. Chip labels, address labels, our own notes' lead and text
 * and every link label are PLAIN text, escaped once by the template's double stashes. A note's
 * lines are trusted HTML: they are Boost Union's own hint strings, which carry markup, filled with
 * values that are integers, escaped urls or names core already formatted - the same strings Boost
 * Union triple-stashes in its own cards.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notice {
    /** @var string A chip or note tone: a good state (public). */
    public const TONE_OK = 'ok';

    /** @var string A chip or note tone: something an editor should look at (hidden course, login only). */
    public const TONE_WARN = 'warn';

    /** @var string A chip or note tone: plain information. */
    public const TONE_INFO = 'info';

    /** @var int The course the notice is about. */
    public int $courseid;

    /** @var bool Whether the page is the course home (the course page, or a single activity's page). */
    public bool $coursehome;

    /** @var bool Whether the notice carries the promotion section (addresses), which only course editors get. */
    public bool $promote = false;

    /** @var array Chips in bar order, key => ['key' => string, 'label' => string, 'tone' => string]. */
    public array $chips = [];

    /** @var array Address rows, key => ['key' => string, 'label' => string, 'sub' => string, 'url' => string]. */
    public array $links = [];

    /**
     * @var array Note rows, key => ['key' => string, 'tone' => string, 'lead' => string, 'text' => string,
     *      'lines' => string[] (trusted HTML), 'link' => ['url' => string, 'label' => string]|null].
     */
    public array $notes = [];

    /** @var array Bar actions, key => ['key' => string, 'label' => string, 'url' => string]. */
    public array $actions = [];

    /**
     * A notice about one course, empty until the builder adds items.
     *
     * @param int $courseid The course.
     * @param bool $coursehome Whether the page is the course home.
     */
    public function __construct(int $courseid, bool $coursehome) {
        $this->courseid = $courseid;
        $this->coursehome = $coursehome;
    }

    /**
     * Add a chip to the bar.
     *
     * @param string $key The item's key.
     * @param string $label Plain text.
     * @param string $tone One of the TONE_* constants.
     * @return void
     */
    public function add_chip(string $key, string $label, string $tone): void {
        $this->chips[$key] = ['key' => $key, 'label' => $label, 'tone' => $tone];
    }

    /**
     * Add an address row: a label, a short line under it and the address itself.
     *
     * @param string $key The item's key.
     * @param string $label Plain text.
     * @param string $sub Plain text.
     * @param string $url The address, raw (out(false)).
     * @return void
     */
    public function add_link(string $key, string $label, string $sub, string $url): void {
        $this->links[$key] = ['key' => $key, 'label' => $label, 'sub' => $sub, 'url' => $url];
    }

    /**
     * Add a note of this theme's own: a bold lead, a sentence and an optional link, all plain.
     *
     * @param string $key The item's key.
     * @param string $tone One of the TONE_* constants.
     * @param string $lead Plain text shown in bold before the sentence; '' for none.
     * @param string $text Plain text.
     * @param array|null $link ['url' => string (raw), 'label' => string (plain)], or null.
     * @return void
     */
    public function add_note(string $key, string $tone, string $lead, string $text, ?array $link = null): void {
        $this->notes[$key] = [
            'key' => $key,
            'tone' => $tone,
            'lead' => $lead,
            'text' => $text,
            'lines' => [],
            'link' => $link,
        ];
    }

    /**
     * Add a note made of Boost Union's hint strings, one line per string.
     *
     * @param string $key The item's key.
     * @param string $tone One of the TONE_* constants.
     * @param array $lines Trusted HTML strings, in reading order.
     * @return void
     */
    public function add_hint(string $key, string $tone, array $lines): void {
        $this->notes[$key] = [
            'key' => $key,
            'tone' => $tone,
            'lead' => '',
            'text' => '',
            'lines' => array_values($lines),
            'link' => null,
        ];
    }

    /**
     * Add an action to the bar, shown whether the notice is folded or not.
     *
     * @param string $key The item's key.
     * @param string $label Plain text.
     * @param string $url The address, raw (out(false)).
     * @return void
     */
    public function add_action(string $key, string $label, string $url): void {
        $this->actions[$key] = ['key' => $key, 'label' => $label, 'url' => $url];
    }

    /**
     * Whether the notice says nothing at all, in which case nothing is drawn.
     *
     * @return bool True when no chip, address, note or action was added.
     */
    public function is_empty(): bool {
        return !$this->chips && !$this->links && !$this->notes && !$this->actions;
    }
}
