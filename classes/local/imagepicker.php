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
 * Theme Dimensions - The image fields' element: tool_imagepicker where installed, core's file manager otherwise.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use theme_dimensions\local\landing\publicfiles;

/**
 * The element every image field of the theme is drawn with, and the size each one is stored at.
 *
 * tool_imagepicker is optional and asked for by class name, as local_page and tool_courserating are
 * ({@see \theme_dimensions\output\category_pages}). Where it is installed an image field is its element: a
 * file manager for one web image with a crop dialog, keeping the field's aspect ratio where it has one and
 * scaling a crop down to the size the image is shown at. Where it is not, the field is core's file manager
 * with the very same options, which is what it was before: the picker's own options are dropped from the
 * array, and storage is the same draft area either way, so nothing about saving a field depends on which
 * element drew it.
 *
 * The share picture's profile is the Open Graph canvas itself ({@see publicfiles::OG_WIDTH}): cropped to
 * that shape and size, it is what the route's derivative shows without a second, centred crop, and the
 * derivative stays the one place that holds the 600 KB WhatsApp accepts - an upload that is never cropped
 * still goes through it.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class imagepicker {
    /** @var string The plugin's element helper, asked for by name: the plugin is optional. */
    public const ELEMENT = '\\tool_imagepicker\\element';

    /** @var string The core element an image field falls back to. */
    public const FILEMANAGER = 'filemanager';

    /** @var string A course's share picture, the source of its landing page's og:image. */
    public const PROFILE_SHARE = 'share';

    /** @var string An institutional badge, on a course's landing page or on a category. */
    public const PROFILE_CREST = 'crest';

    /** @var string A category's background image, also the source of its public page's og:image. */
    public const PROFILE_HERO = 'hero';

    /** @var string A category card's still. */
    public const PROFILE_COVER = 'cover';

    /**
     * @var array The picker's options per profile.
     *
     * The share picture is the og:image canvas exactly. A crest is shown fitted inside a 100 px box and as a
     * poster's watermark, so 400 px covers both at a 3x screen with any shape the crest has. The background
     * image fills the width of the page behind the band (and, cut from its centre, is the category's og:image,
     * which needs no more than 1200 px), so a full HD width is the most a screen asks of it. The card's still
     * is about 640 x 300 on the page, twice that for a 2x screen. Only the share picture fixes a shape: the
     * other three are framed by the stylesheet, which fits or covers them.
     */
    private const PROFILES = [
        self::PROFILE_SHARE => [
            'cropaspectratio' => publicfiles::OG_WIDTH / publicfiles::OG_HEIGHT,
            'cropmaxwidth' => publicfiles::OG_WIDTH,
            'cropmaxheight' => publicfiles::OG_HEIGHT,
        ],
        self::PROFILE_CREST => [
            'cropmaxwidth' => 400,
            'cropmaxheight' => 400,
        ],
        self::PROFILE_HERO => [
            'cropmaxwidth' => 1920,
            'cropmaxheight' => 1080,
        ],
        self::PROFILE_COVER => [
            'cropmaxwidth' => 1280,
            'cropmaxheight' => 600,
        ],
    ];

    /**
     * Whether the image picker can be used: its helper class exists and has the shape this theme calls.
     *
     * @param string|null $element The helper class; tests pass a stand-in or a class that does not exist.
     * @return bool
     */
    public static function available(?string $element = null): bool {
        $element = $element ?? self::ELEMENT;
        return class_exists($element) && method_exists($element, 'register') && defined($element . '::TYPE');
    }

    /**
     * The element type an image field is created with, registered with QuickForm when it is the picker's.
     *
     * @param string|null $element The helper class; tests pass a stand-in or a class that does not exist.
     * @return string The picker's element type, or 'filemanager'.
     */
    public static function element_type(?string $element = null): string {
        $element = $element ?? self::ELEMENT;
        if (!self::available($element)) {
            return self::FILEMANAGER;
        }
        $element::register();
        return (string) constant($element . '::TYPE');
    }

    /**
     * A field's file manager options, with the picker's own added when the picker draws it.
     *
     * The base options win where they overlap, so a caller's explicit choice is never overridden by a
     * profile; without the picker the array is returned as it came, byte for byte.
     *
     * @param array $options The field's file manager options.
     * @param string $profile One of the PROFILE_* constants.
     * @param string|null $element The helper class; tests pass a stand-in or a class that does not exist.
     * @return array The options for the element {@see element_type()} answers.
     * @throws \coding_exception When the profile is not one of the PROFILE_* constants.
     */
    public static function options(array $options, string $profile, ?string $element = null): array {
        if (!array_key_exists($profile, self::PROFILES)) {
            throw new \coding_exception('Unknown image profile: ' . $profile);
        }
        if (!self::available($element)) {
            return $options;
        }
        return $options + self::PROFILES[$profile];
    }

    /**
     * Put the picker in the place of a file manager another plugin added to a form, under the same name.
     *
     * For the course form's picture fields, which customfield_picture draws: the field's byte limit is read
     * off the element, so a limit an administrator changed on the field still holds. That is the limit core
     * already resolved for the current user ({@see \MoodleQuickForm_filemanager::getMaxbytes()}), and handing it
     * back resolves to the same one: a value under the site's, or -1 for a user who may ignore limits. The
     * types are not carried over (the element has no getter for them): customfield_picture accepts
     * 'web_image', which is exactly what the picker accepts when it is given none.
     *
     * The new element takes the old one's place, before whatever followed it (the field's `_static`
     * description), because QuickForm can only insert before a named element. The name, and so the draft
     * area core prepares and saves, is unchanged.
     *
     * Nothing happens without the picker, for a name the form does not hold, or for an element that is not
     * a plain file manager (already a picker, or something else under that name).
     *
     * @param \MoodleQuickForm $mform The form.
     * @param string $name The element's name.
     * @param string $profile One of the PROFILE_* constants.
     * @param string|null $element The helper class; tests pass a stand-in or a class that does not exist.
     * @return bool Whether the element was replaced.
     */
    public static function replace_filemanager(
        \MoodleQuickForm $mform,
        string $name,
        string $profile,
        ?string $element = null
    ): bool {
        if (!self::available($element) || !$mform->elementExists($name)) {
            return false;
        }
        $old = $mform->getElement($name);
        if (get_class($old) !== \MoodleQuickForm_filemanager::class) {
            return false;
        }

        $options = self::options([
            'maxbytes' => $old->getMaxbytes(),
            'maxfiles' => 1,
            'subdirs' => 0,
        ], $profile, $element);
        $type = self::element_type($element);
        $new = $mform->createElement($type, $name, $old->getLabel(), null, $options);

        $next = self::next_named_element($mform, $name);
        $mform->removeElement($name);
        if ($next === null) {
            $mform->addElement($new);
        } else {
            $mform->insertElementBefore($new, $next);
        }
        return true;
    }

    /**
     * The name of the first named element after the given one, or null when it is the last.
     *
     * @param \MoodleQuickForm $mform The form.
     * @param string $name The element's name.
     * @return string|null
     */
    private static function next_named_element(\MoodleQuickForm $mform, string $name): ?string {
        // The keys are positions with holes: removeElement() unsets its slot and renumbers nothing, so the
        // array is walked by its keys, never counted.
        $index = $mform->_elementIndex[$name];
        foreach ($mform->_elements as $position => $element) {
            if ($position <= $index) {
                continue;
            }
            $following = (string) $element->getName();
            if ($following !== '' && $mform->elementExists($following)) {
                return $following;
            }
        }
        return null;
    }
}
