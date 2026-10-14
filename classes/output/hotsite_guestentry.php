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
 * Theme Dimensions - The interstitial page of the hotsite's guest entry
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use theme_dimensions\local\hotsite\guestaccess;

/**
 * The page an anonymous visitor of the guest entry's address gets: a message and one form.
 *
 * The form posts the session key and {@see guestaccess::ENTER_PARAM} to the hotsite's own
 * address, where request::handle() logs the visitor in as the guest and sends them to the
 * course. The module hotsiteguest submits it as soon as the page loads; the button is the same
 * submission for a browser without JavaScript. A robot fetching the address for a link preview
 * runs no script and submits nothing, so it reads this page's head tags - the hotsite's own,
 * registered by the request - and never opens a guest session.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hotsite_guestentry implements \core\output\named_templatable, \core\output\renderable {
    /** @var string Component name for get_string calls. */
    private const COMPONENT = 'theme_dimensions';

    /** @var string The id of the form, which the module is handed. */
    public const FORM_ID = 'theme_dimensions-guestentry';

    /**
     * Constructor.
     *
     * @param \stdClass $course The course record.
     * @param \core\url $action Where the form posts: the hotsite's own address.
     */
    public function __construct(
        /** @var \stdClass The course record. */
        private readonly \stdClass $course,
        /** @var \core\url Where the form posts: the hotsite's own address. */
        private readonly \core\url $action,
    ) {
    }

    /**
     * The template rendering this renderable.
     *
     * @param \core\output\renderer_base $renderer The renderer.
     * @return string The template name.
     */
    public function get_template_name(\core\output\renderer_base $renderer): string {
        return 'theme_dimensions/hotsite_guestentry';
    }

    /**
     * Export the page context.
     *
     * The course name travels in the plain spelling: the template's double stash escapes it once.
     *
     * @param \core\output\renderer_base $output The renderer.
     * @return \stdClass The template context.
     */
    public function export_for_template(\core\output\renderer_base $output): \stdClass {
        $context = \core\context\course::instance($this->course->id);
        $name = format_string($this->course->fullname, true, ['context' => $context, 'escape' => false]);
        return (object) [
            'formid' => self::FORM_ID,
            'action' => $this->action->out(false),
            'field' => guestaccess::ENTER_PARAM,
            'sesskey' => sesskey(),
            'heading' => get_string('hotsite_guestentryheading', self::COMPONENT, $name),
            'message' => get_string('hotsite_guestentrymessage', self::COMPONENT),
            'button' => get_string('hotsite_guestentrybutton', self::COMPONENT),
        ];
    }
}
