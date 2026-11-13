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
 * Theme Dimensions - The filter-groups setting of the category showcase
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use core_course\customfield\course_handler;

/**
 * A multiselect over the site's select-type course custom fields, refusing more than four.
 *
 * The filter groups are an allowlist validated against the live vocabulary,
 * never a free text. Four is the design's cap, not a limit of the layout: the
 * groups live in the Filters panel, where four short groups take about two
 * thirds of its width at 1280px and a fifth short one would still fit (one with
 * many options would wrap). {@see facets::fields()} applies the same cap when it
 * reads the stored shortnames, so an over-long stored value still renders its
 * first four; this class keeps the form from storing a selection the page
 * would silently truncate.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filterfields_setting extends \admin_setting_configmultiselect {
    /**
     * Constructor: the choices are read from the site's course custom fields at form time.
     *
     * @param string $name The setting name, 'theme_dimensions/categoryfilterfields'.
     * @param string $visiblename The localised title.
     * @param string $description The localised description.
     */
    public function __construct(string $name, string $visiblename, string $description) {
        parent::__construct($name, $visiblename, $description, [], null);
    }

    /**
     * The select-type course custom fields of the site, shortname => name.
     *
     * Read lazily: settings.php is included on every admin page and during
     * install, when the handler may have nothing to offer yet.
     *
     * @return bool True (the parent's contract) once the choices are loaded.
     */
    public function load_choices() {
        if (is_array($this->choices)) {
            return true;
        }
        $this->choices = [];
        if (during_initial_install()) {
            return true;
        }
        foreach (course_handler::create()->get_fields() as $field) {
            if ($field->get('type') !== 'select') {
                continue;
            }
            $this->choices[$field->get('shortname')] = format_string($field->get('name'), true, [
                'context' => \context_system::instance(),
                'escape' => false,
            ]);
        }
        return true;
    }

    /**
     * Refuse a selection past the cap; store the rest as the parent does.
     *
     * @param mixed $data The submitted selection.
     * @return string '' on success, the error message otherwise.
     */
    public function write_setting($data) {
        if (is_array($data) && count($data) > facets::MAX_FIELDS) {
            return get_string('categoryfilterfields_toomany', 'theme_dimensions', facets::MAX_FIELDS);
        }
        return parent::write_setting($data);
    }
}
