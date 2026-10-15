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
 * Theme Dimensions - The course notice renderable
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use core\output\named_templatable;
use core\output\renderable;
use core\output\renderer_base;
use theme_dimensions\local\coursenotice\notice;

/**
 * The one notice region of a course page: a bar of chips and actions, and the detail under it.
 *
 * Every decision about WHAT is said is {@see \theme_dimensions\local\coursenotice\builder}'s;
 * this class decides only how it is drawn. A notice carrying the promotion section (a course editor's)
 * can be folded to its bar, where the addresses keep a copy button each; the fold is the person's
 * preference, unset meaning unfolded, written by the AMD module through core's preference service.
 * Any other notice (a learner's, or an editor's on a course without a landing page) is short, has no fold
 * and is always open - the guest account cannot hold a preference at all, and a folded switched role
 * or hidden course hint is a hint nobody reads.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_notice implements named_templatable, renderable {
    /** @var string The user preference holding the fold: 1 folded, 0 or unset unfolded. */
    public const PREFERENCE_COLLAPSED = 'theme_dimensions_coursenotice_collapsed';

    /** @var string The prefix of the region's id and of its addresses' ids. */
    public const ID_PREFIX = 'dim-coursenotice-';

    /** @var notice What the notice says. */
    protected notice $notice;

    /** @var bool Whether the notice is drawn folded. */
    protected bool $collapsed;

    /** @var bool Whether the fold may be written as a preference: a real account, never the guest. */
    protected bool $canpersist;

    /**
     * A notice drawn with a given fold.
     *
     * @param notice $notice What the notice says.
     * @param bool $collapsed Whether it is drawn folded; ignored for a notice without the promotion section.
     * @param bool $canpersist Whether the fold may be written as a preference.
     */
    public function __construct(notice $notice, bool $collapsed, bool $canpersist) {
        $this->notice = $notice;
        $this->collapsed = $notice->promote && $collapsed;
        $this->canpersist = $canpersist;
    }

    /**
     * The notice for the current user, folded as they left it.
     *
     * The guest account holds no preference, so it is never folded; whether the notice can be folded
     * at all is the constructor's rule.
     *
     * @param notice $notice What the notice says.
     * @return self The renderable.
     */
    public static function for_current_user(notice $notice): self {
        $canpersist = isloggedin() && !isguestuser();
        $collapsed = $canpersist && (bool) get_user_preferences(self::PREFERENCE_COLLAPSED, 0);
        return new self($notice, $collapsed, $canpersist);
    }

    /**
     * The template this renderable is drawn with.
     *
     * @param renderer_base $renderer The renderer.
     * @return string The template name.
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'theme_dimensions/course_notice';
    }

    /**
     * The template context of theme_dimensions/course_notice.
     *
     * @param renderer_base $output The renderer.
     * @return array The context.
     */
    public function export_for_template(renderer_base $output): array {
        $component = 'theme_dimensions';
        $uniqid = self::ID_PREFIX . $this->notice->courseid;
        $copied = get_string('landing_addresscopied', $component);

        $links = [];
        foreach ($this->notice->links as $link) {
            $links[] = $link + [
                'addressid' => $uniqid . '-' . $link['key'],
                'copied' => $copied,
                'quicklabel' => get_string('coursenotice_copyquick', $component, $link['label']),
                'copylabel' => get_string('coursenotice_copy', $component, $link['label']),
                'openlabel' => get_string('coursenotice_open', $component, $link['label']),
            ];
        }
        $notes = [];
        foreach ($this->notice->notes as $note) {
            $notes[] = $note + ['haslines' => !empty($note['lines'])];
        }

        return [
            'uniqid' => $uniqid,
            'courseid' => $this->notice->courseid,
            'promote' => $this->notice->promote,
            'title' => get_string($this->notice->promote ? 'coursenotice_promotitle' : 'coursenotice_title', $component),
            'toggle' => $this->notice->promote,
            'collapsed' => $this->collapsed,
            'expanded' => !$this->collapsed,
            'canpersist' => $this->canpersist,
            'preference' => self::PREFERENCE_COLLAPSED,
            'togglelabel' => get_string('coursenotice_toggle', $component),
            'chips' => array_values($this->notice->chips),
            'haschips' => !empty($this->notice->chips),
            'actions' => array_values($this->notice->actions),
            'links' => $links,
            'notes' => $notes,
            'hasdetail' => !empty($links) || !empty($notes),
        ];
    }
}
