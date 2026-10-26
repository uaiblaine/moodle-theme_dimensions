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
 * Theme Dimensions - The navbar's controls that move into the user menu on phones
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

/**
 * Which of the navbar's controls make room on a phone, and the user menu entries that stand in for them.
 *
 * Below the md breakpoint a navbar that carries every control (the edit mode switch, the way into focus mode, the
 * notifications, the messaging drawer, local_mail's inbox and the user menu) leaves the brand a few pixels. The
 * phone keeps the hamburger, the brand, the notifications and the user menu; the others become entries at the top of
 * the user menu, and the page's own two (focus mode and the edit switch) are in the context bar as well
 * ({@see readingbar}). Only the controls the navbar really printed move, so each is decided from the navbar's plugin
 * output, as the subsystems and plugins rendered it, or from the predicate the control itself is drawn under:
 *
 * - The messaging drawer: message_popup's toggle region is in the output.
 * - Mail: local_mail's navbar region is in the output.
 * - Focus mode: the page can switch in place ({@see focusmode::available()}), the condition of the navbar's entry.
 * - Edit mode: core's own predicate for its switch, $page->user_allowed_editing().
 *
 * The entries call the hidden controls themselves (amd/src/phonemenu.js), so each action keeps one implementation,
 * and the stylesheet hides a control only once the user menu holds its entry (scss/phonenav.scss): with the script
 * off, or a user menu that is not there (a visitor's), the navbar stays as it was. Nothing here renders.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class phonenav {
    /** @var string Component name, used for every string. */
    public const COMPONENT = 'theme_dimensions';

    /** @var string The entry that opens the messaging drawer. */
    public const MESSAGES = 'messages';

    /** @var string The entry that opens local_mail's inbox. */
    public const MAIL = 'mail';

    /** @var string The entry that switches the activity into focus mode. */
    public const FOCUS = 'focus';

    /** @var string The entry that flips the edit mode switch. */
    public const EDIT = 'edit';

    /** @var string The mark of message_popup's messaging region in the navbar's plugin output. */
    public const MESSAGES_MARK = 'data-region="popover-region-messages"';

    /** @var string The mark of local_mail's navbar region in the navbar's plugin output. */
    public const MAIL_MARK = 'id="local-mail-navbar"';

    /**
     * The user menu entries for the page being rendered, in the order they are listed.
     *
     * @param string $pluginoutput The navbar's plugin output as the subsystems and plugins rendered it.
     * @param \moodle_page $page The page being rendered.
     * @return array Each entry: key (a constant of this class), url, icon (Font Awesome classes), label, counted
     *      (whether the entry shows the unread count of the control it stands for).
     */
    public static function entries(string $pluginoutput, \moodle_page $page): array {
        $entries = [];
        if (str_contains($pluginoutput, self::MESSAGES_MARK)) {
            $entries[] = self::entry(
                self::MESSAGES,
                new \moodle_url('/message/index.php'),
                'fa-regular fa-comment',
                get_string('messages', 'message'),
                true
            );
        }
        // The plugin is optional; its name is asked of it only where it is installed.
        if (str_contains($pluginoutput, self::MAIL_MARK) && \core_component::get_component_directory('local_mail') !== null) {
            $entries[] = self::entry(
                self::MAIL,
                new \moodle_url('/local/mail/view.php', ['t' => 'inbox']),
                'fa-regular fa-envelope',
                get_string('pluginname', 'local_mail'),
                true
            );
        }
        if (focusmode::available($page)) {
            $entries[] = self::entry(
                self::FOCUS,
                null,
                'fa-solid fa-book-open-reader',
                get_string('focusmode_enter', self::COMPONENT),
                false
            );
        }
        if ($page->user_allowed_editing()) {
            $entries[] = self::entry(
                self::EDIT,
                null,
                'fa-solid fa-pen',
                get_string($page->user_is_editing() ? 'turneditingoff' : 'turneditingon'),
                false
            );
        }

        return $entries;
    }

    /**
     * One entry, as the template reads it.
     *
     * An action that is not a page (focus mode and edit mode post a form) has no address of its own: the link is
     * the page itself and the module runs the action.
     *
     * @param string $key A constant of this class.
     * @param \moodle_url|null $url Where the entry goes, or null for an action.
     * @param string $icon Font Awesome classes.
     * @param string $label The entry's text, plain.
     * @param bool $counted Whether it shows an unread count.
     * @return array
     */
    private static function entry(string $key, ?\moodle_url $url, string $icon, string $label, bool $counted): array {
        return [
            'key' => $key,
            'url' => $url === null ? '#' : $url->out(false),
            'isaction' => $url === null,
            'icon' => $icon,
            'label' => $label,
            'counted' => $counted,
        ];
    }
}
