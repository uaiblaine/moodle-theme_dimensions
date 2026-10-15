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
 * Theme Dimensions - Course form after_form_definition hook callback
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\course;

use theme_dimensions\local\hotsite\guestaccess;
use theme_dimensions\local\hotsite\links;
use theme_dimensions\local\hotsite\offer;
use theme_dimensions\local\hotsite\publicaccess;
use theme_dimensions\local\hotsitefields;

/**
 * Adjusts the hotsite part of the course settings form.
 *
 * Three edits, about the fields this theme provisions and not expressible
 * through the custom field configuration itself: a course which already has
 * a hotsite gains its public address and a link to the page, an event whose
 * enrolment methods set no deadline is told so (round 6, decision D9), and
 * the fields that only apply to some models, modalities and formats are
 * hidden while they do not (decisions D8 and D10).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class after_form_definition {
    /** @var string Name of the static element carrying the link to the hotsite. */
    public const LINK_ELEMENT = 'theme_dimensions_hotsitelink';

    /** @var string Name of the static element warning an event that takes enrolments with no deadline. */
    public const DEADLINE_ELEMENT = 'theme_dimensions_nodeadline';

    /**
     * Adjust the course form's hotsite fields.
     *
     * @param \core_course\hook\after_form_definition $hook The hook.
     */
    public static function callback(\core_course\hook\after_form_definition $hook): void {
        self::add_hotsite_link($hook->formwrapper->get_course(), $hook->mform);
        self::warn_open_ended_event($hook->formwrapper->get_course(), $hook->mform);
        self::hide_inapplicable_fields($hook->mform);
    }

    /**
     * Warn an event whose enrolment methods set no deadline (decision D9).
     *
     * Nothing in core closes an enrolment at the course's dates, so such an event keeps taking
     * enrolments while it is being held, and its hotsite has no deadline to print. Like the link
     * above, the warning follows the STORED page model and the stored methods, never the form: it
     * describes the course as saved, and the methods are edited on their own page. It sits right
     * under the modality field, the fact an editor reads next.
     *
     * @param \stdClass $course The course being edited (no id when it is being created).
     * @param \MoodleQuickForm $mform The course form.
     * @return void
     */
    private static function warn_open_ended_event(\stdClass $course, \MoodleQuickForm $mform): void {
        global $OUTPUT;

        $anchor = 'customfield_' . hotsitefields::SHORTNAME_MODALITY;
        if (empty($course->id) || $course->id == SITEID || !$mform->elementExists($anchor)) {
            return;
        }
        if (hotsitefields::values($course->id)->model !== hotsitefields::MODEL_EVENT) {
            return;
        }
        $undated = offer::undated((int) $course->id);
        if (!$undated) {
            return;
        }

        $context = \core\context\course::instance($course->id);
        $names = [];
        foreach ($undated as $instance) {
            $name = trim((string) $instance->name);
            $names[] = $name !== ''
                ? format_string($name, true, ['context' => $context, 'escape' => false])
                : get_string('pluginname', 'enrol_' . $instance->enrol);
        }
        $message = get_string('hotsite_nodeadline', hotsitefields::COMPONENT, implode(', ', $names));
        $html = $OUTPUT->notification(s($message), \core\output\notification::NOTIFY_WARNING, false);

        $warning = $mform->createElement('static', self::DEADLINE_ELEMENT, '', $html);
        $mform->insertElementBefore($warning, $anchor);
        $mform->insertElementBefore($mform->removeElement($anchor, false), self::DEADLINE_ELEMENT);
        $description = $anchor . '_static';
        if ($mform->elementExists($description)) {
            $mform->insertElementBefore($mform->removeElement($description, false), self::DEADLINE_ELEMENT);
        }
    }

    /**
     * Hide the round-6 fields while the rest of the form says they do not apply.
     *
     * - The format and the live classes belong to courses: hidden on an event.
     * - Live classes belong to tutored courses: hidden while the format is self-paced.
     * - When the live classes happen: hidden while the box is not ticked.
     * - The in-person meetings belong to hybrid offerings: hidden otherwise.
     *
     * The in-person workload belongs to hybrid offerings too and is NOT hidden: it is a
     * customfield_number, a float element, and a hideIf on it stops the course form from saving
     * under JavaScript (measured with Behat: the save waits forever on core_course/formatchooser).
     * Its description says it applies to hybrid offerings, the validation compares it with the
     * total only there, and the page reads it only there.
     *
     * A hidden field keeps the value it had and is submitted with it, which is why the page
     * reads the fields through {@see hotsitefields::coherent()} and the validation refuses only
     * what the editor can see. Every rule is set on the control AND on its '_static'
     * description, the second element core adds for a field that has one; core's form script
     * hides a static element by its data-name. Values are compared as the strings the selects
     * submit.
     *
     * @param \MoodleQuickForm $mform The course form.
     * @return void
     */
    private static function hide_inapplicable_fields(\MoodleQuickForm $mform): void {
        $model = 'customfield_' . hotsitefields::SHORTNAME_MODEL;
        $modality = 'customfield_' . hotsitefields::SHORTNAME_MODALITY;
        $format = 'customfield_' . hotsitefields::SHORTNAME_FORMAT;
        $liveclasses = 'customfield_' . hotsitefields::SHORTNAME_LIVECLASSES;
        $event = (string) hotsitefields::MODEL_EVENT;
        $selfpaced = (string) hotsitefields::FORMAT_SELFPACED;

        $rules = [
            [hotsitefields::SHORTNAME_FORMAT, $model, 'eq', $event],
            [hotsitefields::SHORTNAME_LIVECLASSES, $model, 'eq', $event],
            [hotsitefields::SHORTNAME_LIVECLASSES, $format, 'eq', $selfpaced],
            [hotsitefields::SHORTNAME_LIVECLASSESWHEN, $model, 'eq', $event],
            [hotsitefields::SHORTNAME_LIVECLASSESWHEN, $format, 'eq', $selfpaced],
            [hotsitefields::SHORTNAME_LIVECLASSESWHEN, $liveclasses, 'notchecked', '1'],
            [hotsitefields::SHORTNAME_MEETINGS, $modality, 'neq', (string) hotsitefields::MODALITY_HYBRID],
        ];
        foreach ($rules as [$shortname, $dependenton, $condition, $value]) {
            $element = 'customfield_' . $shortname;
            if (!$mform->elementExists($element) || !$mform->elementExists($dependenton)) {
                continue;
            }
            $mform->hideIf($element, $dependenton, $condition, $value);
            if ($mform->elementExists($element . '_static')) {
                $mform->hideIf($element . '_static', $dependenton, $condition, $value);
            }
        }
    }

    /**
     * Offer the course's hotsite address and a link to the page once the course actually has one.
     *
     * The page model is what turns a hotsite on - hotsite.php redirects to the
     * standard enrolment page without it - so it is also what decides whether
     * there is anything to look at. The value is read from the database rather
     * than from the form, which is the point: the link opens the SAVED page,
     * and an editor arriving to check "how it turned out" is looking at what
     * the last save produced.
     *
     * The address is the public one, {@see links::share()}: the course's short
     * address, minted on the first visit to the form by an editor who may edit
     * the fields - or earlier, by the course notice on the course page, which
     * mints for editors holding moodle/course:update
     * ({@see \theme_dimensions\local\coursenotice\builder}) - and shown
     * as text beside the button, because an address that is dictated needs to be read.
     *
     * A course whose guest access needs no password gets a second row, the guest
     * entry's address ({@see links::guest_share()}, minted here beside the first),
     * which skips the hotsite and opens the course as a guest. It is driven by the
     * stored instances like the link by the stored model, and a course that is not
     * public is told the address only takes in readers who are already logged in:
     * an anonymous visitor of a non-public course gets the login page, as on the
     * hotsite itself.
     *
     * Placement is the reason for the three calls below. QuickForm can only
     * insert ahead of a named element, and repositioning is done by removing
     * and re-inserting - the idiom HTML_QuickForm documents for itself. So the
     * link goes in ahead of the model select, and the select and its
     * description are then moved back over it, which lands the link directly
     * underneath them however the admin has sorted the category.
     *
     * @param \stdClass $course The course being edited (no id when it is being created).
     * @param \MoodleQuickForm $mform The course form.
     */
    private static function add_hotsite_link(\stdClass $course, \MoodleQuickForm $mform): void {
        global $OUTPUT;

        if (empty($course->id) || $course->id == SITEID) {
            return;
        }

        // The anchor for the placement below, and absent for an editor who may not edit the fields.
        $modelelement = 'customfield_' . hotsitefields::SHORTNAME_MODEL;
        if (!$mform->elementExists($modelelement)) {
            return;
        }

        if (!hotsitefields::values($course->id)->model) {
            return;
        }

        $url = links::share((int) $course->id);
        $guest = null;
        if (guestaccess::available($course)) {
            $guest = [
                'url' => links::guest_share((int) $course->id)->out(false),
                'addressid' => 'theme_dimensions-hotsiteguestaddress-' . $course->id,
                'addresslabel' => get_string('hotsite_guestaddress', hotsitefields::COMPONENT),
                'addresssub' => get_string('hotsite_guestaddresssub', hotsitefields::COMPONENT),
                'label' => get_string('hotsite_guestviewlink', hotsitefields::COMPONENT),
                'notpublic' => publicaccess::is_public((int) $course->id)
                    ? ''
                    : get_string('hotsite_guestaddressnotpublic', hotsitefields::COMPONENT),
            ];
        }
        $html = $OUTPUT->render_from_template('theme_dimensions/course_hotsite_link', [
            'guest' => $guest,
            'url' => $url->out(false),
            'addressid' => 'theme_dimensions-hotsiteaddress-' . $course->id,
            'addresslabel' => get_string('hotsite_address', hotsitefields::COMPONENT),
            'copylabel' => get_string('hotsite_copyaddress', hotsitefields::COMPONENT),
            'copied' => get_string('hotsite_addresscopied', hotsitefields::COMPONENT),
            'label' => get_string('hotsite_viewlink', hotsitefields::COMPONENT),
            'newwindow' => get_string('opensinnewwindowbracketed'),
            'note' => get_string('hotsite_viewlinknote', hotsitefields::COMPONENT),
            'routeroff' => links::routing_enabled() ? '' : get_string('hotsite_addressrouteroff', hotsitefields::COMPONENT),
        ]);

        $link = $mform->createElement('static', self::LINK_ELEMENT, '', $html);
        $mform->insertElementBefore($link, $modelelement);
        $mform->insertElementBefore($mform->removeElement($modelelement, false), self::LINK_ELEMENT);
        $descriptionelement = $modelelement . '_static';
        if ($mform->elementExists($descriptionelement)) {
            $mform->insertElementBefore($mform->removeElement($descriptionelement, false), self::LINK_ELEMENT);
        }
    }
}
