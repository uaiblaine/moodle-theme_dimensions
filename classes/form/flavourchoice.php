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
 * Theme Dimensions - The form of the choice page: one radio per offered look
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * The person's look: the site default and each offered flavour, a POST with the session key.
 *
 * The page a menu item opens without JavaScript, and the page the preferences page links to. Showing
 * it writes nothing; only its submission does, through flavourchoice::choose(), which checks the
 * answer against the setting again.
 *
 * Custom data: options (id => plain label, the site default first) and, optionally, marks (id => the
 * rendered theme_dimensions/look_mark, printed before the label).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flavourchoice extends \moodleform {
    /**
     * One radio per look, the page to come back to, save and cancel.
     *
     * A radio's text is printed raw by core's form template, so each label goes in ESCAPED, after its mark.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $radios = [];
        $marks = $this->_customdata['marks'] ?? [];
        foreach ($this->_customdata['options'] as $id => $label) {
            $radios[] = $mform->createElement('radio', 'flavour', '', ($marks[$id] ?? '') . s($label), (int) $id);
        }
        // No separator: core lays a group's elements out in a flex row, where a <br> is an empty item that wraps
        // onto a line of its own after a label wide enough to fill one. scss/look.scss stacks the radios instead.
        $mform->addGroup($radios, 'flavourgroup', get_string('lookmenu', 'theme_dimensions'), '', false);
        $mform->setType('flavour', PARAM_INT);

        $mform->addElement('hidden', 'returnurl');
        $mform->setType('returnurl', PARAM_LOCALURL);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Refuse an answer the form did not offer.
     *
     * @param array $data The submitted values.
     * @param array $files The submitted files.
     * @return array Errors by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!array_key_exists((int) ($data['flavour'] ?? -1), $this->_customdata['options'])) {
            $errors['flavourgroup'] = get_string('required');
        }

        return $errors;
    }
}
