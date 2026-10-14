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
 * Theme Dimensions - Base of the recommendations
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\recommendation;

use theme_boost_union\recommendation\recommendation;

/**
 * The shared part of this theme's Boost Union recommendations.
 *
 * Boost Union's categories are a fixed list that a plugin cannot extend, so this theme's items sit
 * in whichever of them fits and are recognised by name: every id starts with {@see ID_PREFIX} and
 * every title with "Dimensions". Each item decides its status once per instance, because the overview
 * sorts by status and would otherwise ask the database again for every comparison; the manager
 * builds new instances on every call, so nothing is stale across requests.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends recommendation {
    /** @var string The start of every id of this theme's recommendations. */
    public const ID_PREFIX = 'dimensions_';

    /** @var string The component of the language strings. */
    protected const COMPONENT = 'theme_dimensions';

    /**
     * @var string|null The status decided for this instance, null before the first call. Not named
     *     `$status`: Boost Union's own per-class cache is the static `recommendation::$status`, and a
     *     non-static property of that name in a subclass is a fatal error.
     */
    private ?string $decidedstatus = null;

    /**
     * Decide the status from the current state of the site.
     *
     * @return string One of the recommendation status constants.
     */
    abstract protected function evaluate(): string;

    /**
     * Return the recommendation status, decided once per instance.
     *
     * @return string
     */
    public function get_status(): string {
        if ($this->decidedstatus === null) {
            $this->decidedstatus = $this->evaluate();
        }
        return $this->decidedstatus;
    }

    /**
     * Forget the decided status, so the next call reads the site again.
     *
     * @return void
     */
    protected function forget_status(): void {
        $this->decidedstatus = null;
    }
}
