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

use theme_dimensions\local\focusmode;
use theme_dimensions\local\focusmodefields;
use theme_dimensions\local\landing\guestaccess;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\landing\offer;
use theme_dimensions\local\landing\publicaccess;
use theme_dimensions\local\landingfields;
use theme_dimensions\local\trail\trail;

/**
 * Adjusts the parts of the course settings form made of the fields this theme provisions.
 *
 * Four edits, not expressible through the custom field configuration itself: a course which already has a landing page
 * gains its public address and a link to the page, an event whose enrolment methods set no deadline is told so, the
 * landing page fields that only apply to some models, modalities and formats are hidden while they do not, and the
 * focus mode box leaves the form while focus mode's master switch is off.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class after_form_definition {
    /** @var string Name of the static element carrying the link to the landing page. */
    public const LINK_ELEMENT = 'theme_dimensions_landinglink';

    /** @var string Name of the static element warning an event that takes enrolments with no deadline. */
    public const DEADLINE_ELEMENT = 'theme_dimensions_nodeadline';

    /**
     * Adjust the course form's landing page fields.
     *
     * @param \core_course\hook\after_form_definition $hook The hook.
     */
    public static function callback(\core_course\hook\after_form_definition $hook): void {
        self::add_landing_link($hook->formwrapper->get_course(), $hook->mform);
        self::warn_open_ended_event($hook->formwrapper->get_course(), $hook->mform);
        self::hide_inapplicable_fields($hook->mform);
        self::hide_unavailable_model($hook->formwrapper->get_course(), $hook->mform);
        self::remove_focusmode_field($hook->mform);
    }

    /**
     * Leave the trail line out of the page model select while trails are not available.
     *
     * Only the choice goes; the line stays in the field's configuration ({@see trail::hides_option()}). A
     * course already saved as a trail keeps the choice, so the form still shows what the course holds and a
     * save leaves it as it is: the page reads such a course as a course without steps.
     *
     * @param \stdClass $course The course being edited (no id when it is being created).
     * @param \MoodleQuickForm $mform The course form.
     * @return void
     */
    private static function hide_unavailable_model(\stdClass $course, \MoodleQuickForm $mform): void {
        $name = 'customfield_' . landingfields::SHORTNAME_MODEL;
        if (!$mform->elementExists($name) || !trail::hides_option(landingfields::SHORTNAME_MODEL, landingfields::MODEL_TRAIL)) {
            return;
        }
        if (!empty($course->id) && landingfields::model((int) $course->id) === landingfields::MODEL_TRAIL) {
            return;
        }
        $element = $mform->getElement($name);
        if (!$element instanceof \MoodleQuickForm_select) {
            return;
        }
        // Rebuilt rather than removeOption(): that one searches the element's values, which are unset until the
        // form receives its data, after this hook.
        $offered = [];
        foreach ($element->_options as $option) {
            if ((int) $option['attr']['value'] !== landingfields::MODEL_TRAIL) {
                $offered[$option['attr']['value']] = $option['text'];
            }
        }
        $element->removeOptions();
        $element->loadArray($offered);
    }

    /**
     * Take the focus mode box, its description and its category's header out of the form while the master switch is off.
     *
     * Off, nothing of the feature is loaded, and a box nobody's answer reaches would
     * be a question with no effect. Removing the elements stores nothing: core's
     * data_controller::instance_form_save() returns early for an element absent from the
     * submission (customfield/classes/data_controller.php), so every course keeps its answer for the
     * day the switch is turned back on. The category's header goes too, but only while the field is
     * alone in it: an administrator may have moved other fields into the theme's category.
     *
     * @param \MoodleQuickForm $mform The course form.
     * @return void
     */
    private static function remove_focusmode_field(\MoodleQuickForm $mform): void {
        if (focusmode::master_enabled()) {
            return;
        }
        $element = 'customfield_' . focusmodefields::SHORTNAME;
        if (!$mform->elementExists($element)) {
            return;
        }
        $mform->removeElement($element);
        if ($mform->elementExists($element . '_static')) {
            $mform->removeElement($element . '_static');
        }

        $field = focusmodefields::get_field();
        if (!$field) {
            return;
        }
        $category = $field->get_category();
        $header = 'category_' . $category->get('id');
        if (count($category->get_fields()) === 1 && $mform->elementExists($header)) {
            $mform->removeElement($header);
        }
    }

    /**
     * Warn an event whose enrolment methods set no deadline.
     *
     * Nothing in core closes an enrolment at the course's dates, so such an event keeps taking
     * enrolments while it is being held, and its landing page has no deadline to print. Like the link
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

        $anchor = 'customfield_' . landingfields::SHORTNAME_MODALITY;
        if (empty($course->id) || $course->id == SITEID || !$mform->elementExists($anchor)) {
            return;
        }
        if (landingfields::values($course->id)->model !== landingfields::MODEL_EVENT) {
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
        $message = get_string('landing_nodeadline', landingfields::COMPONENT, implode(', ', $names));
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
     * Hide the format and hybrid fields while the rest of the form says they do not apply.
     *
     * - The format and the live classes belong to courses: hidden on an event.
     * - Live classes belong to tutored courses: hidden while the format is self-paced.
     * - When the live classes happen: hidden while the box is not ticked.
     * - The in-person meetings belong to hybrid offerings: hidden otherwise.
     *
     * The in-person workload belongs to hybrid offerings too and is NOT hidden: it is a
     * customfield_number, a float element, and a hideIf on it stops the course form from saving
     * under JavaScript (the save never completes on core_course/formatchooser).
     * Its description says it applies to hybrid offerings, the validation compares it with the
     * total only there, and the page reads it only there.
     *
     * A hidden field keeps the value it had and is submitted with it, which is why the page
     * reads the fields through {@see landingfields::coherent()} and the validation refuses only
     * what the editor can see. Every rule is set on the control AND on its '_static'
     * description, the second element core adds for a field that has one; core's form script
     * hides a static element by its data-name. Values are compared as the strings the selects
     * submit.
     *
     * @param \MoodleQuickForm $mform The course form.
     * @return void
     */
    private static function hide_inapplicable_fields(\MoodleQuickForm $mform): void {
        $model = 'customfield_' . landingfields::SHORTNAME_MODEL;
        $modality = 'customfield_' . landingfields::SHORTNAME_MODALITY;
        $format = 'customfield_' . landingfields::SHORTNAME_FORMAT;
        $liveclasses = 'customfield_' . landingfields::SHORTNAME_LIVECLASSES;
        $event = (string) landingfields::MODEL_EVENT;
        $selfpaced = (string) landingfields::FORMAT_SELFPACED;

        $rules = [
            [landingfields::SHORTNAME_FORMAT, $model, 'eq', $event],
            [landingfields::SHORTNAME_LIVECLASSES, $model, 'eq', $event],
            [landingfields::SHORTNAME_LIVECLASSES, $format, 'eq', $selfpaced],
            [landingfields::SHORTNAME_LIVECLASSESWHEN, $model, 'eq', $event],
            [landingfields::SHORTNAME_LIVECLASSESWHEN, $format, 'eq', $selfpaced],
            [landingfields::SHORTNAME_LIVECLASSESWHEN, $liveclasses, 'notchecked', '1'],
            [landingfields::SHORTNAME_MEETINGS, $modality, 'neq', (string) landingfields::MODALITY_HYBRID],
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
     * Offer the course's landing page address and a link to the page once the course actually has one.
     *
     * The page model is what turns a landing page on - landing.php redirects to the
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
     * which skips the landing page and opens the course as a guest. It is driven by the
     * stored instances like the link by the stored model, and a course that is not
     * public is told the address only takes in readers who are already logged in:
     * an anonymous visitor of a non-public course gets the login page, as on the
     * landing page itself.
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
    private static function add_landing_link(\stdClass $course, \MoodleQuickForm $mform): void {
        global $OUTPUT;

        if (empty($course->id) || $course->id == SITEID) {
            return;
        }

        // The anchor for the placement below, and absent for an editor who may not edit the fields.
        $modelelement = 'customfield_' . landingfields::SHORTNAME_MODEL;
        if (!$mform->elementExists($modelelement)) {
            return;
        }

        if (!landingfields::values($course->id)->model) {
            return;
        }

        $url = links::share((int) $course->id);
        $guest = null;
        if (guestaccess::available($course)) {
            $guest = [
                'url' => links::guest_share((int) $course->id)->out(false),
                'addressid' => 'theme_dimensions-landingguestaddress-' . $course->id,
                'addresslabel' => get_string('landing_guestaddress', landingfields::COMPONENT),
                'addresssub' => get_string('landing_guestaddresssub', landingfields::COMPONENT),
                'label' => get_string('landing_guestviewlink', landingfields::COMPONENT),
                'notpublic' => publicaccess::is_public((int) $course->id)
                    ? ''
                    : get_string('landing_guestaddressnotpublic', landingfields::COMPONENT),
            ];
        }
        $html = $OUTPUT->render_from_template('theme_dimensions/course_landing_link', [
            'guest' => $guest,
            'url' => $url->out(false),
            'addressid' => 'theme_dimensions-landingaddress-' . $course->id,
            'addresslabel' => get_string('landing_address', landingfields::COMPONENT),
            'copylabel' => get_string('landing_copyaddress', landingfields::COMPONENT),
            'copied' => get_string('landing_addresscopied', landingfields::COMPONENT),
            'label' => get_string('landing_viewlink', landingfields::COMPONENT),
            'newwindow' => get_string('opensinnewwindowbracketed'),
            'note' => get_string('landing_viewlinknote', landingfields::COMPONENT),
            'routeroff' => links::routing_enabled() ? '' : get_string('landing_addressrouteroff', landingfields::COMPONENT),
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
