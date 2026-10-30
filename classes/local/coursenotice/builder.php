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
 * Theme Dimensions - The course notice's decisions
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\coursenotice;

use theme_dimensions\local\landing\guestaccess;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\landing\publicaccess;
use theme_dimensions\local\landingfields;

/**
 * Decides what the one notice region of a course page says, for whoever is looking.
 *
 * The notice replaces Boost Union's stacked "course related hints" cards, in every course format.
 * It holds two kinds of item:
 *
 * - Boost Union's hints, re-implemented item by item from theme_boost_union_get_course_related_hints()
 *   (BU/locallib.php): the same settings, read from theme_boost_union, the same capabilities, the
 *   same strings with the same placeholders and links, the same learner cases. Re-read that function
 *   on every Boost Union update; the parity table is in the original design record (not part of this repository).
 * - The promotion section, for course editors only (moodle/course:update) and only on a course with
 *   a landing page model: the landing page's public address, the guest entry's address where the course offers
 *   it, and who may learn that the course exists - public, listed, unlisted - with what withholds it when
 *   that is not the course's own setting ({@see add_discoverability()}).
 *
 * One thing is generalised, and only one: Boost Union's page gate. Where it asks for
 * /course/view.php this class asks for the course HOME ({@see is_course_home()}), which is the
 * course page for a format that has one and, for a format without one (format_singleactivity, whose
 * course/view.php redirects to the activity), the view page of the course's main activity. The
 * hidden course's forum special case is kept as Boost Union has it, and the switched role hint keeps
 * having no page gate at all. Nothing is ever said outside a course: the site course and a page with
 * no url answer null.
 *
 * Minting: the promotion section spells the addresses through {@see links::share()} and
 * {@see links::guest_share()}, which mint a short code the first time they are asked. They are asked
 * only after the moodle/course:update check, so a learner, a guest or a teacher without editing
 * rights never mints anything by opening a course page; an editor does, once per course and type.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class builder {
    /** @var string The component whose settings and strings the hints are read from. */
    public const BU = 'theme_boost_union';

    /** @var string The capability that admits a reader to the promotion section, and so to minting. */
    public const CAP_UPDATE = 'moodle/course:update';

    /** @var string local_unlistedcourses' publish capability, which earns the "change it" link. */
    public const CAP_PUBLISH = 'local/unlistedcourses:publish';

    /** @var string The capability local_unlistedcourses' course form offers the state select behind. */
    public const CAP_VISIBILITY = 'moodle/course:visibility';

    /** @var string local_unlistedcourses' category state capability, which earns the category note its link. */
    public const CAP_CATEGORYSTATE = 'local/unlistedcourses:managecategorystate';

    /** @var string The category state predicate, read as optional like {@see publicaccess::PREDICATE}. */
    public const CATEGORY_PREDICATE = \local_unlistedcourses\category_discoverability::class;

    /** @var string The course's own discoverability state, as {@see own_state()} spells it: public. */
    public const STATE_PUBLIC = 'public';

    /** @var string The course's own discoverability state: unlisted. */
    public const STATE_UNLISTED = 'unlisted';

    /** @var string The course's own discoverability state: listed, the default. */
    public const STATE_LISTED = 'listed';

    /** @var string[] The forum scripts on which Boost Union swaps the hidden course hint for the forum one. */
    public const FORUM_SCRIPTS = ['/mod/forum/view.php', '/mod/forum/discuss.php', '/mod/forum/post.php'];

    /** @var string Item key: the course is public (chip and note). */
    public const KEY_PUBLIC = 'public';

    /** @var string Item key: the course has a landing page but is not public (chip and note). */
    public const KEY_LOGINONLY = 'loginonly';

    /** @var string Item key: the landing page (chip and address). */
    public const KEY_LANDING = 'landing';

    /** @var string Item key: the guest entry (chip and address). */
    public const KEY_GUEST = 'guest';

    /** @var string Item key: the course is set to public and something else withholds it (chip and note). */
    public const KEY_PUBLICWITHHELD = 'publicwithheld';

    /** @var string Item key: the course's own state is unlisted (chip and note). */
    public const KEY_UNLISTED = 'unlisted';

    /** @var string Item key: a public course withheld because it is hidden (note). */
    public const KEY_PUBLICHIDDEN = 'publichidden';

    /** @var string Item key: a category on the course's path is hidden (chip and note). */
    public const KEY_HIDDENCATEGORY = 'hiddencategory';

    /** @var string Item key: a category on the course's path is unlisted (chip and note). */
    public const KEY_UNLISTEDCATEGORY = 'unlistedcategory';

    /** @var string Item key: the guest entry of a course that is not public (note). */
    public const KEY_GUESTNOTPUBLIC = 'guestnotpublic';

    /** @var string Item key: the site's router is off, so the addresses are long ones (note). */
    public const KEY_ROUTEROFF = 'routeroff';

    /** @var string Item key: Boost Union's hidden course hint (chip and note). */
    public const KEY_HIDDEN = 'hidden';

    /** @var string Item key: Boost Union's hidden course hint on a forum page (note; the chip is KEY_HIDDEN). */
    public const KEY_FORUM = 'forumnotifications';

    /** @var string Item key: Boost Union's guest access hint, for a reader in the course as a guest. */
    public const KEY_GUESTACCESS = 'guestaccess';

    /** @var string Item key: Boost Union's guest enrolment hint, for staff. */
    public const KEY_GUESTENROL = 'guestenrol';

    /** @var string Item key: Boost Union's self enrolment hint, for staff. */
    public const KEY_SELFENROL = 'selfenrol';

    /** @var string Item key: Boost Union's switched role hint, with its way back. */
    public const KEY_SWITCHEDROLE = 'switchedrole';

    /** @var string This theme's component, for its own strings. */
    private const COMPONENT = 'theme_dimensions';

    /**
     * What the notice of this page says, or null when it says nothing.
     *
     * @param \moodle_page $page The page being rendered.
     * @param \core\output\core_renderer $output The renderer, for the one link core draws inside a Boost Union string.
     * @return notice|null The notice, or null outside a course, on a page with no url, or with no item.
     */
    public static function build(\moodle_page $page, \core\output\core_renderer $output): ?notice {
        global $CFG;

        $course = $page->course;
        // SITEID is defined from the site record, so it may be a string: compare both sides as integers.
        if (empty($course->id) || (int) $course->id === (int) SITEID || !$page->has_set_url()) {
            return null;
        }
        // Boost Union's setting values are compared against its own constants, defined in its lib.php.
        require_once($CFG->dirroot . '/theme/boost_union/lib.php');

        $context = \core\context\course::instance((int) $course->id);
        $notice = new notice((int) $course->id, self::is_course_home($page, $course));

        self::add_promotion($notice, $course, $context);
        self::add_hidden_hint($notice, $page, $course, $context);
        self::add_guest_access_hint($notice, $course, $context);
        self::add_guest_enrol_hint($notice, $course, $context);
        self::add_self_enrol_hint($notice, $course, $context, $output);
        self::add_switched_role_hint($notice, $page, $course);

        return $notice->is_empty() ? null : $notice;
    }

    /**
     * Whether the page is the course home: where Boost Union would ask for /course/view.php.
     *
     * A format with a view page lands on /course/view.php. A format without one that names a main
     * activity (core_courseformat\main_activity_interface; format_singleactivity is core's) lands on
     * that activity's own view page: core's course/view.php redirects there, so that is where the
     * course actually opens. The page must be that module's page (its cm) AND its view url, so the
     * activity's other pages - a quiz attempt, a forum discussion - are not course homes.
     *
     * @param \moodle_page $page The page.
     * @param \stdClass $course The page's course.
     * @return bool Whether the page is the course home.
     */
    public static function is_course_home(\moodle_page $page, \stdClass $course): bool {
        if (!$page->has_set_url()) {
            return false;
        }
        $format = course_get_format($course);
        if ($format->has_view_page()) {
            return $page->url->compare(new \core\url('/course/view.php'), URL_MATCH_BASE);
        }
        if (!$format instanceof \core_courseformat\main_activity_interface) {
            return false;
        }
        $main = $format->get_main_activity();
        if (!$main || !$main->url || !$page->cm || (int) $page->cm->id !== (int) $main->id) {
            return false;
        }
        return $page->url->compare($main->url, URL_MATCH_BASE);
    }

    /**
     * The promotion section: addresses, discoverability and the guest entry, for course editors.
     *
     * The capability is asked FIRST, before anything that could mint, and the course home second.
     * The model is read through {@see landingfields::model()}, the cheap reader, so a course without
     * a landing page pays nothing more; the modality only once there is a model.
     *
     * @param notice $notice The notice being built.
     * @param \stdClass $course The course.
     * @param \core\context\course $context The course context.
     * @return void
     */
    private static function add_promotion(notice $notice, \stdClass $course, \core\context\course $context): void {
        if (!has_capability(self::CAP_UPDATE, $context)) {
            return;
        }
        if (!$notice->coursehome) {
            return;
        }
        $courseid = (int) $course->id;
        $model = landingfields::model($courseid);
        if (!$model) {
            return;
        }
        $notice->promote = true;

        $public = publicaccess::is_public($courseid);
        $state = self::own_state($courseid);
        $path = self::path_withholders($course);
        if ($public) {
            $notice->add_chip(self::KEY_PUBLIC, get_string('coursenotice_chip_public', self::COMPONENT), notice::TONE_OK);
        } else if ($state === self::STATE_PUBLIC) {
            $notice->add_chip(
                self::KEY_PUBLICWITHHELD,
                get_string('coursenotice_chip_publicwithheld', self::COMPONENT),
                notice::TONE_WARN
            );
        } else if ($state === self::STATE_UNLISTED) {
            $notice->add_chip(self::KEY_UNLISTED, get_string('coursenotice_chip_unlisted', self::COMPONENT), notice::TONE_WARN);
        } else {
            $notice->add_chip(self::KEY_LOGINONLY, get_string('coursenotice_chip_loginonly', self::COMPONENT), notice::TONE_WARN);
        }
        if ($path[self::KEY_UNLISTEDCATEGORY]) {
            $notice->add_chip(
                self::KEY_UNLISTEDCATEGORY,
                get_string('coursenotice_chip_unlistedcategory', self::COMPONENT),
                notice::TONE_WARN
            );
        }
        if ($path[self::KEY_HIDDENCATEGORY]) {
            $notice->add_chip(
                self::KEY_HIDDENCATEGORY,
                get_string('coursenotice_chip_hiddencategory', self::COMPONENT),
                notice::TONE_WARN
            );
        }
        $notice->add_chip(self::KEY_LANDING, self::landing_chip($courseid, $model), notice::TONE_INFO);

        $guest = guestaccess::available($course);
        if ($guest) {
            $notice->add_chip(self::KEY_GUEST, get_string('coursenotice_chip_guest', self::COMPONENT), notice::TONE_INFO);
        }

        $notice->add_link(
            self::KEY_LANDING,
            get_string('coursenotice_landing', self::COMPONENT),
            get_string('coursenotice_landingsub', self::COMPONENT),
            links::share($courseid)->out(false)
        );
        if ($guest) {
            $notice->add_link(
                self::KEY_GUEST,
                get_string('landing_guestaddress', self::COMPONENT),
                get_string('landing_guestaddresssub', self::COMPONENT),
                links::guest_share($courseid)->out(false)
            );
        }

        $change = null;
        if (self::may_publish($context)) {
            $change = [
                'url' => (new \core\url('/course/edit.php', ['id' => $courseid]))->out(false),
                'label' => get_string('coursenotice_changediscoverability', self::COMPONENT),
            ];
        }
        if ($public) {
            $notice->add_note(
                self::KEY_PUBLIC,
                notice::TONE_OK,
                get_string('coursenotice_publiclead', self::COMPONENT),
                get_string('coursenotice_publictext', self::COMPONENT),
                $change
            );
        } else {
            self::add_discoverability($notice, $course, $context, $state, $path, $change);
            if ($guest) {
                $notice->add_note(
                    self::KEY_GUESTNOTPUBLIC,
                    notice::TONE_INFO,
                    '',
                    get_string('landing_guestaddressnotpublic', self::COMPONENT)
                );
            }
        }
        if (!links::routing_enabled()) {
            $notice->add_note(self::KEY_ROUTEROFF, notice::TONE_INFO, '', get_string('landing_addressrouteroff', self::COMPONENT));
        }
    }

    /**
     * The notes of a course that is not public: its own state, then whatever above it withholds it.
     *
     * The verdict is always the predicate's ({@see publicaccess::is_public()}), read by the caller;
     * this only says why. A course set to public that the predicate refuses gets the "not in effect"
     * note and one note per withholding fact the theme can name - the course hidden, a hidden
     * category, an unlisted category - each read the way local_unlistedcourses' discoverability::are_public()
     * composes it. Should that composition grow a term this does not read, the "not in effect" note
     * still says the setting is withheld, so the notice never claims the course is public.
     *
     * The category notes are said whatever the course's own state, because they withhold the course
     * from listings as well: a hidden category from whoever may not see hidden categories, an unlisted
     * one from whoever local_unlistedcourses' category predicate does not admit
     * ({@see \local_unlistedcourses\access::filter_courses()}).
     *
     * @param notice $notice The notice being built.
     * @param \stdClass $course The course.
     * @param \core\context\course $context The course context.
     * @param string|null $state The course's own state ({@see own_state()}).
     * @param array $path {@see path_withholders()}.
     * @param array|null $change The "change the discoverability" link for whoever may publish, or null.
     * @return void
     */
    private static function add_discoverability(
        notice $notice,
        \stdClass $course,
        \core\context\course $context,
        ?string $state,
        array $path,
        ?array $change
    ): void {
        if ($state === self::STATE_PUBLIC) {
            $notice->add_note(
                self::KEY_PUBLICWITHHELD,
                notice::TONE_WARN,
                get_string('coursenotice_publicwithheldlead', self::COMPONENT),
                get_string('coursenotice_publicwithheldtext', self::COMPONENT),
                $change
            );
            if (!$course->visible) {
                $notice->add_note(
                    self::KEY_PUBLICHIDDEN,
                    notice::TONE_WARN,
                    '',
                    get_string('coursenotice_publichiddentext', self::COMPONENT)
                );
            }
        } else if ($state === self::STATE_UNLISTED) {
            // Leaving the unlisted state is no publishing act: the course form offers it to course:visibility.
            $link = null;
            if (has_capability(self::CAP_VISIBILITY, $context)) {
                $link = [
                    'url' => (new \core\url('/course/edit.php', ['id' => $course->id]))->out(false),
                    'label' => get_string('coursenotice_changediscoverability', self::COMPONENT),
                ];
            }
            $notice->add_note(
                self::KEY_UNLISTED,
                notice::TONE_WARN,
                get_string('coursenotice_unlistedlead', self::COMPONENT),
                get_string('coursenotice_unlistedtext', self::COMPONENT),
                $link
            );
        } else {
            $notice->add_note(
                self::KEY_LOGINONLY,
                notice::TONE_WARN,
                '',
                get_string('coursenotice_loginonlytext', self::COMPONENT),
                $change
            );
        }

        $unlisted = $path[self::KEY_UNLISTEDCATEGORY];
        if ($unlisted) {
            $link = null;
            // Optional like the publish capability: between a deploy and its upgrade it may not be installed.
            if (
                get_capability_info(self::CAP_CATEGORYSTATE)
                && has_capability(self::CAP_CATEGORYSTATE, $unlisted->get_context())
            ) {
                $link = [
                    'url' => (new \core\url('/local/unlistedcourses/category.php', ['id' => $unlisted->id]))->out(false),
                    'label' => get_string('coursenotice_changecategorydiscoverability', self::COMPONENT),
                ];
            }
            // A teacher of the course need not be allowed to discover the category: its name is theirs to read only then.
            if (\local_unlistedcourses\category_access::is_category_discoverable((int) $unlisted->id)) {
                $text = get_string(
                    'coursenotice_unlistedcategorytext',
                    self::COMPONENT,
                    $unlisted->get_formatted_name(['escape' => false])
                );
            } else {
                $text = get_string('coursenotice_unlistedcategoryanon', self::COMPONENT);
            }
            $notice->add_note(self::KEY_UNLISTEDCATEGORY, notice::TONE_WARN, '', $text, $link);
        }
        $hidden = $path[self::KEY_HIDDENCATEGORY];
        if ($hidden) {
            $link = null;
            if (has_capability('moodle/category:manage', $hidden->get_context())) {
                $link = [
                    'url' => (new \core\url('/course/editcategory.php', ['id' => $hidden->id]))->out(false),
                    'label' => get_string('coursenotice_editcategory', self::COMPONENT),
                ];
            }
            $notice->add_note(
                self::KEY_HIDDENCATEGORY,
                notice::TONE_WARN,
                '',
                get_string(
                    'coursenotice_hiddencategorytext',
                    self::COMPONENT,
                    $hidden->get_formatted_name(['escape' => false])
                ),
                $link
            );
        }
    }

    /**
     * The course's own discoverability state, or null where local_unlistedcourses is not there.
     *
     * Only ever explains a "not public" verdict; it decides nothing, so a missing plugin reads as no
     * state and the notice says "login only", as the fail-closed predicate does. Spelled in this
     * class's own constants, so that no caller names the plugin's class where it may be missing.
     *
     * @param int $courseid The course.
     * @return string|null One of the STATE_* constants, or null.
     */
    private static function own_state(int $courseid): ?string {
        if (!class_exists(publicaccess::PREDICATE)) {
            return null;
        }
        return match (\local_unlistedcourses\discoverability::get_state($courseid)) {
            \local_unlistedcourses\discoverability::STATE_PUBLIC => self::STATE_PUBLIC,
            \local_unlistedcourses\discoverability::STATE_UNLISTED => self::STATE_UNLISTED,
            default => self::STATE_LISTED,
        };
    }

    /**
     * The categories on the course's path that withhold it: the nearest unlisted one and the nearest hidden one.
     *
     * The nearest, because that is the category whose own settings a course editor is likeliest to
     * reach; a second one further up is named once the first is dealt with. "Unlisted" is a row of
     * the category itself or of an ancestor, read through local_unlistedcourses'
     * category_discoverability::unlisted_ids(), the same set its course predicate intersects with the
     * path. Hidden categories are read with core_course_category::get()'s $alwaysreturnhidden, because
     * the reader may well not see them - which is the point of saying so.
     *
     * @param \stdClass $course The course.
     * @return array [KEY_UNLISTEDCATEGORY => core_course_category|null, KEY_HIDDENCATEGORY => core_course_category|null].
     */
    private static function path_withholders(\stdClass $course): array {
        $found = [self::KEY_UNLISTEDCATEGORY => null, self::KEY_HIDDENCATEGORY => null];
        $category = \core_course_category::get((int) $course->category, IGNORE_MISSING, true);
        if (!$category) {
            return $found;
        }
        $unlistedids = class_exists(self::CATEGORY_PREDICATE)
            ? \local_unlistedcourses\category_discoverability::unlisted_ids()
            : [];
        // Nearest first: the course's own category, then up the path.
        $ids = array_reverse(array_map('intval', array_filter(explode('/', (string) $category->path))));
        foreach ($ids as $id) {
            $ancestor = \core_course_category::get($id, IGNORE_MISSING, true);
            if (!$ancestor) {
                continue;
            }
            if (!$found[self::KEY_UNLISTEDCATEGORY] && in_array($id, $unlistedids, true)) {
                $found[self::KEY_UNLISTEDCATEGORY] = $ancestor;
            }
            if (!$found[self::KEY_HIDDENCATEGORY] && !$ancestor->visible) {
                $found[self::KEY_HIDDENCATEGORY] = $ancestor;
            }
        }
        return $found;
    }

    /**
     * The landing page chip's label: the model, and the modality beside it when one is stored.
     *
     * Both are the option texts the fields are actually configured with
     * ({@see landingfields::option_label()}), so an administrator's edit of an option shows here too.
     *
     * @param int $courseid The course.
     * @param int $model The stored model value.
     * @return string Plain text.
     */
    private static function landing_chip(int $courseid, int $model): string {
        $modellabel = landingfields::option_label(landingfields::SHORTNAME_MODEL, $model) ?? '';
        $modality = (int) (landingfields::values_many([$courseid], false)[$courseid]->modality ?? 0);
        $modalitylabel = landingfields::option_label(landingfields::SHORTNAME_MODALITY, $modality);
        if ($modalitylabel === null) {
            return get_string('coursenotice_chip_landing', self::COMPONENT, $modellabel);
        }
        return get_string('coursenotice_chip_landingmodality', self::COMPONENT, (object) [
            'model' => $modellabel,
            'modality' => \core_text::strtolower($modalitylabel),
        ]);
    }

    /**
     * Whether the reader may change the course's discoverability, which earns the note its link.
     *
     * local_unlistedcourses is read as optional here: without it, or between a deploy and its upgrade
     * (the capability not installed yet, where has_capability() would emit a debugging notice), the
     * link is simply not offered.
     *
     * @param \core\context\course $context The course context.
     * @return bool Whether the link is offered.
     */
    private static function may_publish(\core\context\course $context): bool {
        if (!class_exists(publicaccess::PREDICATE) || !get_capability_info(self::CAP_PUBLISH)) {
            return false;
        }
        return has_capability(self::CAP_PUBLISH, $context);
    }

    /**
     * Boost Union's hidden course hint, and its forum notifications special case.
     *
     * Mirrors the first block of theme_boost_union_get_course_related_hints(): the setting, the
     * capability and a hidden course; the general text on the course home, replaced on a forum page
     * by the forum text when that second setting is on; the settings link for a course editor.
     *
     * @param notice $notice The notice being built.
     * @param \moodle_page $page The page.
     * @param \stdClass $course The course.
     * @param \core\context\course $context The course context.
     * @return void
     */
    private static function add_hidden_hint(
        notice $notice,
        \moodle_page $page,
        \stdClass $course,
        \core\context\course $context
    ): void {
        if (get_config(self::BU, 'showhintcoursehidden') != THEME_BOOST_UNION_SETTING_SELECT_YES) {
            return;
        }
        if (!has_capability('theme/boost_union:viewhintinhiddencourse', $context)) {
            return;
        }
        if ($course->visible) {
            return;
        }

        $text = null;
        $key = self::KEY_HIDDEN;
        if ($notice->coursehome) {
            $text = get_string('showhintcoursehiddengeneral', self::BU);
        }
        if (
            get_config(self::BU, 'showhintforumnotifications') == THEME_BOOST_UNION_SETTING_SELECT_YES
            && self::on_forum_page($page)
        ) {
            $text = get_string('showhintforumnotifications', self::BU);
            $key = self::KEY_FORUM;
        }
        if ($text === null) {
            return;
        }

        $lines = [$text];
        if (has_capability('moodle/course:update', $context)) {
            $url = new \core\url('/course/edit.php', ['id' => $course->id]);
            $lines[] = get_string('showhintcoursehiddensettingslink', self::BU, ['url' => $url->out()]);
        }
        $notice->add_chip(self::KEY_HIDDEN, get_string('coursenotice_chip_hidden', self::COMPONENT), notice::TONE_WARN);
        $notice->add_hint($key, notice::TONE_WARN, $lines);
    }

    /**
     * Whether the page is one of the forum pages Boost Union's forum special case names.
     *
     * @param \moodle_page $page The page.
     * @return bool Whether it is.
     */
    private static function on_forum_page(\moodle_page $page): bool {
        foreach (self::FORUM_SCRIPTS as $script) {
            if ($page->url->compare(new \core\url($script), URL_MATCH_BASE)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Boost Union's guest access hint: a reader in the course as a guest, with the way to self enrol.
     *
     * Not shown after a role switch, as in Boost Union: a role that may enter without enrolment would
     * otherwise read the guest hint beside the switched role one.
     *
     * @param notice $notice The notice being built.
     * @param \stdClass $course The course.
     * @param \core\context\course $context The course context.
     * @return void
     */
    private static function add_guest_access_hint(notice $notice, \stdClass $course, \core\context\course $context): void {
        global $USER;

        if (get_config(self::BU, 'showhintcourseguestaccess') != THEME_BOOST_UNION_SETTING_SELECT_YES) {
            return;
        }
        if (!$notice->coursehome || !is_guest($context, $USER->id)) {
            return;
        }
        if (is_role_switched($course->id)) {
            return;
        }

        $showselfenrollink = false;
        $plugins = enrol_get_plugins(true);
        foreach (enrol_get_instances($course->id, true) as $instance) {
            if (!isset($plugins[$instance->enrol])) {
                continue;
            }
            if ($plugins[$instance->enrol]->show_enrolme_link($instance)) {
                $showselfenrollink = true;
                break;
            }
        }

        $lines = [get_string('showhintcourseguestaccessgeneral', self::BU, ['role' => role_get_name(get_guest_role())])];
        if ($showselfenrollink) {
            $url = new \core\url('/enrol/index.php', ['id' => $course->id]);
            $lines[] = get_string('showhintcourseguestaccesslink', self::BU, ['url' => $url->out()]);
        }
        $notice->add_chip(self::KEY_GUESTACCESS, get_string('coursenotice_chip_guestaccess', self::COMPONENT), notice::TONE_INFO);
        $notice->add_hint(self::KEY_GUESTACCESS, notice::TONE_INFO, $lines);
    }

    /**
     * Boost Union's guest enrolment hint: a visible course whose guest access is on, for staff.
     *
     * Without a password, or with one when the setting says "always"; the sentence on who gets in
     * follows the site's guest login button; the call to action for whoever may configure guest
     * enrolment. Where the promotion section already shows the guest entry its chip is not repeated.
     *
     * @param notice $notice The notice being built.
     * @param \stdClass $course The course.
     * @param \core\context\course $context The course context.
     * @return void
     */
    private static function add_guest_enrol_hint(notice $notice, \stdClass $course, \core\context\course $context): void {
        global $CFG;

        $setting = get_config(self::BU, 'showhintcourseguestenrol');
        if (
            $setting != THEME_BOOST_UNION_SETTING_GUESTACCESSHINT_WITHOUTPASSWORD
            && $setting != THEME_BOOST_UNION_SETTING_GUESTACCESSHINT_ALWAYS
        ) {
            return;
        }
        if (!has_capability('theme/boost_union:viewhintcourseguestenrol', $context)) {
            return;
        }
        if (!$notice->coursehome || !$course->visible) {
            return;
        }

        foreach (enrol_get_instances($course->id, true) as $instance) {
            if ($instance->enrol != 'guest') {
                continue;
            }
            $passwordset = !empty($instance->password);
            if ($passwordset && $setting != THEME_BOOST_UNION_SETTING_GUESTACCESSHINT_ALWAYS) {
                continue;
            }
            $a = ['courseid' => $course->id];
            $who = $CFG->guestloginbutton == 1
                ? ($passwordset ? 'showhintcourseguestenroleveryonealways' : 'showhintcourseguestenroleveryone')
                : ($passwordset ? 'showhintcourseguestenrolauthonlyalways' : 'showhintcourseguestenrolauthonly');
            $lines = [
                get_string($passwordset ? 'showhintcourseguestenrolhintalways' : 'showhintcourseguestenrolhint', self::BU, $a)
                    . ' ' . get_string($who, self::BU, $a),
            ];
            if (has_capability('enrol/guest:config', $context)) {
                $url = new \core\url('/enrol/instances.php', ['id' => $course->id]);
                $lines[] = get_string(
                    $passwordset ? 'showhintcourseguestenrolcallforactionalways' : 'showhintcourseguestenrolcallforaction',
                    self::BU,
                    ['url' => $url->out(), 'courseid' => $course->id]
                );
            }
            if (!isset($notice->chips[self::KEY_GUEST])) {
                $chip = $passwordset ? 'coursenotice_chip_guestenrolpassword' : 'coursenotice_chip_guestenrol';
                $notice->add_chip(self::KEY_GUESTENROL, get_string($chip, self::COMPONENT), notice::TONE_INFO);
            }
            $notice->add_hint(self::KEY_GUESTENROL, notice::TONE_INFO, $lines);
            return;
        }
    }

    /**
     * Boost Union's self enrolment hint: unrestricted self enrolment in a visible course, for staff.
     *
     * An instance counts when it has no key, takes new enrolments (customint6) and has not ended;
     * each one is described by the shape of its window, exactly as Boost Union does, and named as a
     * link to its settings for whoever may configure self enrolment - the one link core draws here,
     * through the renderer, because it sits inside a Boost Union string's placeholder.
     *
     * @param notice $notice The notice being built.
     * @param \stdClass $course The course.
     * @param \core\context\course $context The course context.
     * @param \core\output\core_renderer $output The renderer.
     * @return void
     */
    private static function add_self_enrol_hint(
        notice $notice,
        \stdClass $course,
        \core\context\course $context,
        \core\output\core_renderer $output
    ): void {
        if (get_config(self::BU, 'showhintcourseselfenrol') != THEME_BOOST_UNION_SETTING_SELECT_YES) {
            return;
        }
        if (!has_capability('theme/boost_union:viewhintcourseselfenrol', $context)) {
            return;
        }
        if (!$notice->coursehome || !$course->visible) {
            return;
        }

        $selfplugin = enrol_get_plugin('self');
        if (!$selfplugin) {
            return;
        }
        // Boost Union reads "now" in the server timezone; a timestamp is the same instant either way.
        $now = time();
        $canconfig = has_capability('enrol/self:config', $context);
        $current = false;
        $lines = [];
        foreach (enrol_get_instances($course->id, true) as $instance) {
            if (
                $instance->enrol != 'self' || !empty($instance->password) || $instance->customint6 != 1
                || (!empty($instance->enrolenddate) && $instance->enrolenddate <= $now)
            ) {
                continue;
            }
            $start = (int) $instance->enrolstartdate;
            $end = (int) $instance->enrolenddate;
            // The shape of the window, Boost Union's six cases in its order, each with its own string.
            if (!$start && !$end) {
                [$stringid, $current] = ['showhintcourseselfenrolunlimited', true];
            } else if (!$start) {
                [$stringid, $current] = ['showhintcourseselfenroluntil', true];
            } else if (!$end && $start > $now) {
                $stringid = 'showhintcourseselfenrolfrom';
            } else if (!$end) {
                [$stringid, $current] = ['showhintcourseselfenrolsince', true];
            } else if ($start > $now) {
                $stringid = 'showhintcourseselfenrolfromuntil';
            } else {
                [$stringid, $current] = ['showhintcourseselfenrolsinceuntil', true];
            }

            $name = $selfplugin->get_instance_name($instance);
            if ($canconfig) {
                $url = new \core\url(
                    '/enrol/editinstance.php',
                    ['courseid' => $course->id, 'id' => $instance->id, 'type' => 'self']
                );
                $name = $output->action_link($url, $name);
            }
            $a = ['name' => $name];
            if ($start) {
                $a['from'] = userdate($start);
                $a['since'] = userdate($start);
            }
            if ($end) {
                $a['until'] = userdate($end);
            }
            $lines[] = get_string($stringid, self::BU, $a);
        }
        if (!$lines) {
            return;
        }

        // Every counted instance is open now or opens later, so "not current" is Boost Union's "future".
        $opening = get_string($current ? 'showhintcourseselfenrolstartcurrently' : 'showhintcourseselfenrolstartfuture', self::BU);
        array_unshift($lines, $opening);
        if ($canconfig) {
            $lines[] = get_string('showhintcourseselfenrolinstancecallforaction', self::BU);
        }
        $notice->add_chip(self::KEY_SELFENROL, get_string('coursenotice_chip_selfenrol', self::COMPONENT), notice::TONE_INFO);
        $notice->add_hint(self::KEY_SELFENROL, notice::TONE_INFO, $lines);
    }

    /**
     * Boost Union's switched role hint, on every page of the course, with the way back as a bar action.
     *
     * The way back is in the bar rather than in the folded detail, so a folded notice still offers it.
     *
     * @param notice $notice The notice being built.
     * @param \moodle_page $page The page.
     * @param \stdClass $course The course.
     * @return void
     */
    private static function add_switched_role_hint(notice $notice, \moodle_page $page, \stdClass $course): void {
        global $USER;

        // Strict, as Boost Union compares this one setting.
        if (get_config(self::BU, 'showswitchedroleincourse') !== THEME_BOOST_UNION_SETTING_SELECT_YES) {
            return;
        }
        if (!is_role_switched($course->id)) {
            return;
        }
        $info = \core\user::get_user_navigation_info($USER, $page);
        $role = $info->metadata['rolename'] ?? '';
        $url = new \core\url('/course/switchrole.php', [
            'id' => $course->id,
            'sesskey' => sesskey(),
            'switchrole' => 0,
            'returnurl' => $page->url->out_as_local_url(false),
        ]);
        $notice->add_chip(self::KEY_SWITCHEDROLE, get_string('coursenotice_chip_switchedrole', self::COMPONENT), notice::TONE_WARN);
        $notice->add_hint(self::KEY_SWITCHEDROLE, notice::TONE_INFO, [get_string('switchedroleto', self::BU, ['role' => $role])]);
        $notice->add_action(self::KEY_SWITCHEDROLE, get_string('switchrolereturn'), $url->out(false));
    }
}
