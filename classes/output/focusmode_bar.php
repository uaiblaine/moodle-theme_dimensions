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
 * Theme Dimensions - Focus mode bar renderable
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use theme_dimensions\local\focusmode;

/**
 * Builds the sub-context of the bar which replaces Boost Union's navbar in focus mode.
 *
 * Every decision it renders belongs to {@see focusmode}; this class only reads the page
 * and the layout context Boost Union already built. Two of its keys come straight from
 * that context - navbarcolorclasses and databstheme - so an organisation's flavour, or an
 * administrator's navbar colour, recolours the bar with no code here at all.
 *
 * Every value below lands in a Mustache double stash and therefore leaves in the PLAIN
 * spelling, which is why the activity and course names are formatted with escape => false. The header
 * actions and the edit switch are the exceptions - they are finished HTML core rendered,
 * and the template puts them in triple stashes.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class focusmode_bar implements \core\output\renderable, \core\output\templatable {
    /** @var string Component name for get_string calls. */
    private const COMPONENT = 'theme_dimensions';

    /**
     * Constructor.
     *
     * @param \moodle_page $page The page being rendered, with its cm and course already set.
     * @param array $layoutcontext The layout template context Boost Union built.
     */
    public function __construct(
        /** @var \moodle_page The page being rendered. */
        private readonly \moodle_page $page,
        /** @var array The layout template context Boost Union built. */
        private readonly array $layoutcontext,
    ) {
    }

    /**
     * Export the bar context.
     *
     * @param \core\output\renderer_base $output The renderer.
     * @return array The template context.
     */
    public function export_for_template(\core\output\renderer_base $output): array {
        $cm = $this->page->cm;
        $courseid = (int) $this->page->course->id;
        $expanded = focusmode::wants_expanded();
        $moreitems = $this->more_items();
        $editswitch = $this->edit_switch($output);

        /* One control with two renderings. A course whose own switch is on gets the plain
           link back to the section; a manual session gets a POST which also clears the
           session flag, so leaving does not land on a page that re-focuses itself. */
        $leaveform = !focusmode::enabled_for_course($courseid) && focusmode::in_session($courseid);

        return [
            'title' => $cm->get_formatted_name(['escape' => false]),
            'coursename' => $this->course_name(),
            'closeurl' => focusmode::close_url($cm)->out(false),
            'closelabel' => get_string('focusmode_close', self::COMPONENT),
            'closetitle' => get_string('focusmode_close_title', self::COMPONENT),
            'leaveform' => $leaveform,
            'sesskey' => sesskey(),
            'cmid' => (int) $cm->id,
            'actionurl' => (new \moodle_url('/theme/dimensions/focus.php'))->out(false),
            'headeractions' => $this->header_actions(),
            'expanded' => $expanded,
            'widthlabel' => get_string('focusmode_width', self::COMPONENT),
            'moreitems' => $moreitems,
            'editswitch' => $editswitch,
            'hasmore' => $moreitems !== [] || $editswitch !== '',
            'navbarcolorclasses' => (string) ($this->layoutcontext['navbarcolorclasses'] ?? ''),
            'databstheme' => (string) ($this->layoutcontext['databstheme'] ?? ''),
        ];
    }

    /**
     * The course short name, in the plain spelling the template's double stash expects.
     *
     * @return string The formatted short name.
     */
    private function course_name(): string {
        $course = $this->page->course;

        return format_string(
            $course->shortname,
            true,
            ['context' => \context_course::instance($course->id), 'escape' => false]
        );
    }

    /**
     * The page's header actions, wrapped so the template can loop over them.
     *
     * These are the manual completion button and the automatic status badge, queued by
     * core's own activity_header::export_for_template(). The manual toggle's listener is
     * delegated from the document, so the button works wherever its markup lands.
     *
     * @return array List of ['html' => string].
     */
    private function header_actions(): array {
        $actions = [];
        foreach ($this->page->get_header_actions() as $action) {
            $actions[] = ['html' => $action];
        }

        return $actions;
    }

    /**
     * Core's edit switch, for a user who may turn editing on.
     *
     * Turning editing on is how staff leave focus mode, so this is the one control in the
     * overflow menu which changes what the next request renders.
     *
     * @param \core\output\renderer_base $output The renderer.
     * @return string The rendered switch, or the empty string.
     */
    private function edit_switch(\core\output\renderer_base $output): string {
        if (!$this->page->user_allowed_editing() || !method_exists($output, 'edit_switch')) {
            return '';
        }

        return (string) $output->edit_switch();
    }

    /**
     * The links of the "More" overflow menu.
     *
     * What staff would otherwise lose with the navbar and the secondary navigation row:
     * the secondary navigation's own nodes, the language links and Boost Union's
     * accessibility support page. Nothing is switched off server-side to produce this -
     * the nodes are read, not moved - so the ordinary page is one click away and
     * unchanged.
     *
     * @return array List of ['url' => string, 'text' => string].
     */
    private function more_items(): array {
        $items = [];

        /* The active node is the page already open (on an activity page, core marks the
           module's own tab active), so it is never a choice; whatever remains is the navigation
           the learner lost with the tabs - a student usually has nothing left here, a teacher
           has the settings and the reports. */
        if ($this->page->has_secondary_navigation()) {
            foreach ($this->page->secondarynav->children as $node) {
                if (!empty($node->isactive)) {
                    continue;
                }
                $url = $node->action();
                if (!$url instanceof \moodle_url) {
                    continue;
                }
                $items[] = ['url' => $url->out(false), 'text' => (string) $node->text];
            }
        }

        foreach ($this->language_nodes() as $node) {
            $url = $node['url'] ?? null;
            $items[] = [
                'url' => $url instanceof \moodle_url ? $url->out(false) : (string) $url,
                'text' => (string) ($node['text'] ?? ''),
            ];
        }

        // Boost Union's own accessibility support page, when its include put one in the context.
        $support = $this->layoutcontext['accessibilitysupportlink'] ?? null;
        if (!empty($support)) {
            $items[] = [
                'url' => $support instanceof \moodle_url ? $support->out(false) : (string) $support,
                'text' => (string) ($this->layoutcontext['accessibilitysupportpagetitle'] ?? ''),
            ];
        }

        return $items;
    }

    /**
     * The language menu's nodes, as core's language_menu exported them.
     *
     * Core hands an empty array for a logged-in, non-guest user, and so does this.
     *
     * @return array List of node arrays.
     */
    private function language_nodes(): array {
        $langmenu = $this->layoutcontext['langmenu'] ?? [];
        if (!is_array($langmenu)) {
            return [];
        }

        return (array) ($langmenu['nodes'] ?? []);
    }
}
