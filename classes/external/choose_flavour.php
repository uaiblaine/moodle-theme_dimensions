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
 * Theme Dimensions - Web service: record the look a person chose from the user menu
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use theme_dimensions\local\flavourchoice;

/**
 * Records the look a person picked in the user menu's Look submenu, for that person only.
 *
 * The menu's client module calls it and reloads the page, which then primes the new flavour. Every
 * decision is flavourchoice::choose()'s, the one write path the choice page's form shares: the site
 * default, or a flavour the setting offers now. Like the focus mode switch (set_focus_mode) it writes
 * one personal setting of the caller and logs no event; core logs none for a preference either.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class choose_flavour extends external_api {
    /**
     * Parameters: the chosen flavour.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'flavourid' => new external_value(PARAM_INT, 'The chosen flavour id, or 0 for the site default.'),
        ]);
    }

    /**
     * Record the choice.
     *
     * @param int $flavourid The chosen flavour id, or 0 for the site default.
     * @return array chosen: the flavour id now in force for the caller.
     * @throws \moodle_exception When the guest calls it, or the flavour is not offered.
     */
    public static function execute(int $flavourid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['flavourid' => $flavourid]);
        $context = \context_system::instance();
        self::validate_context($context);
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        if (!flavourchoice::choose((int) $params['flavourid'])) {
            throw new \moodle_exception('flavourchoice_unavailable', 'theme_dimensions');
        }

        return ['chosen' => flavourchoice::chosen_id()];
    }

    /**
     * Returns: the flavour now in force.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'chosen' => new external_value(PARAM_INT, 'The flavour id now in force for the caller, or 0 for the site default.'),
        ]);
    }
}
