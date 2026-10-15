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
 * Theme Dimensions - One usable enrolment method on the landing page
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\landing;

/**
 * One enrolment method the current user can actually use, as the resolver saw it.
 *
 * A value object: the resolver decides, the page renders. TYPE_FALLBACK entries
 * are the one exception to "usable" - they mark instances of enrol plugins this
 * theme has no adapter for; whether they render anything is decided at page
 * render time by calling the plugin's enrol_page_hook() (falsy = hidden, the
 * same contract core's enrol/index.php applies).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class method {
    /** @var string Self enrolment (enrol_self), immediate access. */
    public const TYPE_SELF = 'self';

    /** @var string Application subject to approval (enrol_apply). */
    public const TYPE_APPLY = 'apply';

    /** @var string Paid enrolment (enrol_fee). */
    public const TYPE_FEE = 'fee';

    /** @var string Guest access (enrol_guest), always secondary. */
    public const TYPE_GUEST = 'guest';

    /** @var string Unknown enrol plugin - rendered through its own enrol_page_hook(). */
    public const TYPE_FALLBACK = 'fallback';

    /**
     * Constructor. Use the for_*() factories instead.
     *
     * @param string $type One of the TYPE_* constants.
     * @param \stdClass $instance The {enrol} instance record.
     * @param bool $requirespassword Whether the method asks for a key/password (self, guest).
     * @param float|null $cost The enrolment cost (fee only).
     * @param string|null $currency The cost currency (fee only).
     */
    private function __construct(
        /** @var string One of the TYPE_* constants. */
        public readonly string $type,
        /** @var \stdClass The {enrol} instance record. */
        public readonly \stdClass $instance,
        /** @var bool Whether the method asks for a key/password (self, guest). */
        public readonly bool $requirespassword = false,
        /** @var float|null The enrolment cost (fee only). */
        public readonly ?float $cost = null,
        /** @var string|null The cost currency (fee only). */
        public readonly ?string $currency = null,
    ) {
    }

    /**
     * A usable self enrolment instance.
     *
     * @param \stdClass $instance The {enrol} instance record.
     * @param bool $requirespassword Whether the instance carries an enrolment key.
     * @return self The method.
     */
    public static function for_self(\stdClass $instance, bool $requirespassword): self {
        return new self(self::TYPE_SELF, $instance, $requirespassword);
    }

    /**
     * A usable application (enrol_apply) instance.
     *
     * @param \stdClass $instance The {enrol} instance record.
     * @return self The method.
     */
    public static function for_apply(\stdClass $instance): self {
        return new self(self::TYPE_APPLY, $instance);
    }

    /**
     * A usable paid (enrol_fee) instance.
     *
     * @param \stdClass $instance The {enrol} instance record.
     * @param float $cost The resolved cost (instance cost, or the plugin default).
     * @param string $currency The currency code.
     * @return self The method.
     */
    public static function for_fee(\stdClass $instance, float $cost, string $currency): self {
        return new self(self::TYPE_FEE, $instance, false, $cost, $currency);
    }

    /**
     * A guest access (enrol_guest) instance.
     *
     * @param \stdClass $instance The {enrol} instance record.
     * @param bool $requirespassword Whether guest access asks for a password.
     * @return self The method.
     */
    public static function for_guest(\stdClass $instance, bool $requirespassword): self {
        return new self(self::TYPE_GUEST, $instance, $requirespassword);
    }

    /**
     * An instance of an enrol plugin this theme has no adapter for.
     *
     * @param \stdClass $instance The {enrol} instance record.
     * @return self The method.
     */
    public static function for_fallback(\stdClass $instance): self {
        return new self(self::TYPE_FALLBACK, $instance);
    }
}
