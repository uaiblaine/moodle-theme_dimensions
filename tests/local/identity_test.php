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
 * Theme Dimensions - Tests for what identifies a person in the theme's own surfaces
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the identity resolver: which field each setting shows, its fallbacks, and what it costs.
 *
 * A person is always the complete record core puts in the session (get_complete_user_data()), because the
 * custom profile fields are read from there. Every refusal stands beside a control that is shown.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(identity::class)]
final class identity_test extends \advanced_testcase {
    /**
     * Start from a site with no custom profile field.
     *
     * Another plugin of the site may create fields when it is installed, and the vocabulary is every field of the
     * site: each test names the whole of it.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $DB->delete_records('user_info_field');
        \cache::make(identity::COMPONENT, identity::CACHE)->purge();
    }

    /**
     * A person as the session holds them, logged in.
     *
     * @param array $record The user's columns and profile_field_ values.
     * @return \stdClass The complete user record, which is also $USER.
     */
    private function person(array $record): \stdClass {
        // Under a sink, so no user_created observer of another plugin rewrites the names set here.
        $sink = $this->redirectEvents();
        $user = self::getDataGenerator()->create_user($record);
        $sink->close();
        $complete = get_complete_user_data('id', $user->id);
        $this->setUser($complete);

        return $complete;
    }

    /**
     * A custom profile field.
     *
     * @param string $shortname The field's shortname.
     * @param string $datatype The field's type.
     * @param array $more Further columns.
     * @return \stdClass The field's row.
     */
    private function field(string $shortname, string $datatype = 'text', array $more = []): \stdClass {
        return self::getDataGenerator()->create_custom_profile_field(
            $more + ['shortname' => $shortname, 'name' => ucfirst($shortname), 'datatype' => $datatype]
        );
    }

    /**
     * Store one of the identity settings.
     *
     * @param string $setting The setting's name.
     * @param string $value Its value.
     * @return void
     */
    private function set(string $setting, string $value): void {
        set_config($setting, $value, identity::COMPONENT);
    }

    /**
     * With nothing stored, the name is fullname() twice, there is no line, and nothing is read.
     *
     * @return void
     */
    public function test_the_defaults_are_the_full_name_and_cost_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        $this->field('rank');
        $user = $this->person(['firstname' => 'Ana', 'lastname' => 'Ribeiro', 'alternatename' => 'Maj. Ribeiro']);
        $this->assertSame('fullname', get_config(identity::COMPONENT, identity::SETTING_NAVBAR), 'The installed default.');
        $this->assertSame('fullname', get_config(identity::COMPONENT, identity::SETTING_TITLE), 'The installed default.');
        $this->assertSame('', get_config(identity::COMPONENT, identity::SETTING_LINES), 'The installed default.');
        $this->assertSame('', get_config(identity::COMPONENT, identity::SETTING_PATTERNS), 'The installed default.');

        // Once as installed, and once as a site where the settings were never stored.
        foreach ([true, false] as $stored) {
            if (!$stored) {
                foreach ([identity::SETTING_NAVBAR, identity::SETTING_TITLE, identity::SETTING_LINES] as $setting) {
                    unset_config($setting, identity::COMPONENT);
                }
                unset_config(identity::SETTING_PATTERNS, identity::COMPONENT);
                $this->assertFalse(get_config(identity::COMPONENT, identity::SETTING_NAVBAR));
            }
            $reads = $DB->perf_get_reads();
            $this->assertSame(fullname($user), identity::navbar($user));
            $this->assertSame('Ana Ribeiro', identity::navbar($user));
            $this->assertSame('Ana Ribeiro', identity::title($user));
            $this->assertSame([], identity::lines($user));
            $this->assertSame($reads, $DB->perf_get_reads(), 'The defaults read no table.');
            $this->assertFalse(
                \cache::make(identity::COMPONENT, identity::CACHE)->get(identity::CACHE),
                'The defaults do not fill the definitions cache.'
            );
        }

        // The site's name format still decides the full name.
        set_config('fullnamedisplay', 'alternatename');
        $this->assertSame('Maj. Ribeiro', identity::navbar($user));
    }

    /**
     * Each of the two single-field settings shows its own column.
     *
     * @return void
     */
    public function test_a_chosen_column_is_shown(): void {
        $this->resetAfterTest();
        $user = $this->person([
            'firstname' => 'Antônio',
            'lastname' => 'Perovano',
            'alternatename' => ' Cel. Perovano ',
            'username' => 'perovano',
            'email' => 'perovano@example.com',
            'idnumber' => 'RG 77',
        ]);

        $this->set(identity::SETTING_NAVBAR, 'alternatename');
        $this->assertSame('Cel. Perovano', identity::navbar($user), 'Trimmed.');
        $this->assertSame('Antônio Perovano', identity::title($user), 'Control: the other setting is untouched.');

        $this->set(identity::SETTING_TITLE, 'idnumber');
        $this->assertSame('RG 77', identity::title($user));
        foreach (['firstname' => 'Antônio', 'username' => 'perovano', 'email' => 'perovano@example.com'] as $field => $text) {
            $this->set(identity::SETTING_NAVBAR, $field);
            $this->assertSame($text, identity::navbar($user));
        }
    }

    /**
     * An empty or unknown field falls back to the full name, under the full name's own pattern.
     *
     * @return void
     */
    public function test_an_empty_or_unknown_field_falls_back_to_the_full_name(): void {
        $this->resetAfterTest();
        $with = $this->person(['firstname' => 'Ana', 'lastname' => 'Ribeiro', 'alternatename' => 'Maj. Ribeiro']);
        $without = $this->person(['firstname' => 'Bruno', 'lastname' => 'Cardoso', 'alternatename' => '']);
        $this->set(identity::SETTING_NAVBAR, 'alternatename');
        $this->set(identity::SETTING_TITLE, 'alternatename');

        $this->setUser($with);
        $this->assertSame('Maj. Ribeiro', identity::navbar($with), 'Control: a filled field is shown.');
        $this->setUser($without);
        $this->assertSame('Bruno Cardoso', identity::navbar($without));
        $this->assertSame('Bruno Cardoso', identity::title($without));
        $this->setUser($with);

        // A stored key outside the vocabulary: a column of the user record that is not offered, and a field that is gone.
        foreach (['lastname', 'password', 'profile_field_gone', 'profile_field_', 'nonsense'] as $stored) {
            $this->set(identity::SETTING_NAVBAR, $stored);
            $this->assertSame('Ana Ribeiro', identity::navbar($with), $stored);
        }

        // The fallback is masked by the full name's pattern, and not by the empty field's.
        $this->set(identity::SETTING_NAVBAR, 'alternatename');
        $this->set(identity::SETTING_PATTERNS, "fullname=##### *******\nalternatename=***");
        $this->setUser($without);
        $this->assertSame('Bruno •••••••', identity::navbar($without));
    }

    /**
     * The further lines: the vocabulary's order, no empty one, none equal to the title.
     *
     * @return void
     */
    public function test_the_lines_keep_the_order_and_skip_the_empty_and_the_repeated(): void {
        $this->resetAfterTest();
        // The fields' order is their category's and then their own, whatever order they were ticked in.
        $this->field('unit', 'text', ['sortorder' => 2]);
        $this->field('rank', 'text', ['sortorder' => 1]);
        $user = $this->person([
            'firstname' => 'Ana',
            'lastname' => 'Ribeiro',
            'alternatename' => 'Maj. Ribeiro',
            'username' => 'ribeiro',
            'idnumber' => '',
            'profile_field_rank' => 'Major',
            'profile_field_unit' => '1st Battalion',
        ]);

        $this->set(identity::SETTING_LINES, 'profile_field_unit,idnumber,username,profile_field_rank,alternatename,fullname');
        $this->assertSame(
            ['Maj. Ribeiro', 'ribeiro', 'Major', '1st Battalion'],
            identity::lines($user),
            'The full name is the title, and the ID number is empty.'
        );

        // Control for the repeated line: with another title, the full name is a line and the alternate name is not.
        $this->set(identity::SETTING_TITLE, 'alternatename');
        $this->assertSame(['Ana Ribeiro', 'ribeiro', 'Major', '1st Battalion'], identity::lines($user));

        // Control for the empty line: once filled, the ID number is shown in its place.
        $user->idnumber = 'RG 77';
        $this->assertSame(['Ana Ribeiro', 'ribeiro', 'RG 77', 'Major', '1st Battalion'], identity::lines($user));

        $this->set(identity::SETTING_LINES, '');
        $this->assertSame([], identity::lines($user));
    }

    /**
     * A pattern is applied to its own field wherever the field is shown.
     *
     * @return void
     */
    public function test_a_pattern_masks_its_field_everywhere(): void {
        $this->resetAfterTest();
        $user = $this->person(['firstname' => 'Ana', 'lastname' => 'Ribeiro', 'username' => '12345678909', 'idnumber' => '12']);
        $this->set(identity::SETTING_NAVBAR, 'username');
        $this->set(identity::SETTING_TITLE, 'username');
        $this->set(identity::SETTING_LINES, 'username,idnumber');
        $this->assertSame('12345678909', identity::navbar($user), 'Control: no pattern, the value as stored.');

        $this->set(identity::SETTING_PATTERNS, 'username=***.###.###-**');
        $this->assertSame('•••.456.789-••', identity::navbar($user));
        $this->assertSame('•••.456.789-••', identity::title($user));
        $this->assertSame(['12'], identity::lines($user), 'The masked line is the title; the other field has no pattern.');

        $this->set(identity::SETTING_TITLE, 'fullname');
        $this->assertSame(['•••.456.789-••', '12'], identity::lines($user));

        // A changed setting is read at once: the parsed patterns follow the stored value.
        $this->set(identity::SETTING_PATTERNS, "username=###.***.***-##\nidnumber=#*");
        $this->assertSame(['123.•••.•••-09', '1•'], identity::lines($user));
    }

    /**
     * A custom profile field is read from the session's record, as plain text.
     *
     * @return void
     */
    public function test_a_custom_profile_field_is_shown_as_plain_text(): void {
        $this->resetAfterTest();
        $this->field('rank');
        $this->field('post', 'menu', ['param1' => "Commander\nInstructor"]);
        $this->field('motto');
        $user = $this->person([
            'firstname' => 'Ana',
            'lastname' => 'Ribeiro',
            'profile_field_rank' => 'Major',
            'profile_field_post' => 'Instructor',
            'profile_field_motto' => 'Fire & <b>rescue</b>',
        ]);

        $this->set(identity::SETTING_NAVBAR, 'profile_field_rank');
        $this->assertSame('Major', identity::navbar($user));
        $this->set(identity::SETTING_NAVBAR, 'profile_field_post');
        $this->assertSame('Instructor', identity::navbar($user));
        // Tags go, and the ampersand stays plain: the template escapes once.
        $this->set(identity::SETTING_NAVBAR, 'profile_field_motto');
        $this->assertSame('Fire & rescue', identity::navbar($user));

        // The record is the session's: a login path that loaded no profile reads as an empty field.
        $bare = clone $user;
        unset($bare->profile);
        $this->assertSame('Ana Ribeiro', identity::navbar($bare));
        $bare->profile = ['motto' => '   '];
        $this->assertSame('Ana Ribeiro', identity::navbar($bare));
    }

    /**
     * A custom profile field is shown only while core shows it to the viewer.
     *
     * The session's record carries every field, the ones "not visible" included, so the value is there to leak.
     *
     * @return void
     */
    public function test_a_custom_profile_field_follows_cores_visibility(): void {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $this->resetAfterTest();
        $this->field('note', 'text', ['visible' => PROFILE_VISIBLE_NONE]);
        $this->field('own', 'text', ['visible' => PROFILE_VISIBLE_PRIVATE]);
        $this->field('staff', 'text', ['visible' => PROFILE_VISIBLE_TEACHERS]);
        $this->field('open', 'text', ['visible' => PROFILE_VISIBLE_ALL]);
        $values = [
            'profile_field_note' => 'Under review',
            'profile_field_own' => 'Mine',
            'profile_field_staff' => 'Seen by staff',
            'profile_field_open' => 'Public',
        ];
        $this->set(identity::SETTING_LINES, implode(',', array_keys($values)));

        $student = $this->person(['firstname' => 'Ana', 'lastname' => 'Ribeiro'] + $values);
        $this->assertSame('Under review', $student->profile['note'], 'Precondition: the hidden value is in the record.');
        $this->assertSame(['Mine', 'Seen by staff', 'Public'], identity::lines($student));
        $this->set(identity::SETTING_NAVBAR, 'profile_field_note');
        $this->assertSame('Ana Ribeiro', identity::navbar($student), 'A field nobody sees falls back.');

        // Control: an account holding moodle/user:viewalldetails sees its own hidden field.
        $manager = $this->person(['firstname' => 'Bruno', 'lastname' => 'Cardoso'] + $values);
        $role = create_role('Details', 'details', '');
        assign_capability('moodle/user:viewalldetails', CAP_ALLOW, $role, \context_system::instance());
        role_assign($role, $manager->id, \context_system::instance());
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($manager);
        $this->assertSame(['Under review', 'Mine', 'Seen by staff', 'Public'], identity::lines($manager));
        $this->assertSame('Under review', identity::navbar($manager));

        // Another person's record is judged as core judges it for the viewer: the student sees only what is public.
        $this->setUser($student);
        $this->assertSame(['Public'], identity::lines($manager));
    }

    /**
     * A column of the user record is shown to its owner alone.
     *
     * The columns carry no visibility rule here, so a caller handing in another person's record gets that
     * person's full name and nothing else of theirs.
     *
     * @return void
     */
    public function test_another_persons_columns_are_not_shown(): void {
        $this->resetAfterTest();
        $columns = ['alternatename' => '', 'username' => 'cardoso', 'idnumber' => 'RG 9'];
        $other = $this->person(['firstname' => 'Bruno', 'lastname' => 'Cardoso'] + $columns);
        $columns = ['alternatename' => '', 'username' => 'ribeiro', 'idnumber' => 'RG 7'];
        $viewer = $this->person(['firstname' => 'Ana', 'lastname' => 'Ribeiro'] + $columns);
        $this->set(identity::SETTING_NAVBAR, 'username');
        $this->set(identity::SETTING_TITLE, 'email');
        $this->set(identity::SETTING_LINES, 'username,email,idnumber,alternatename,firstname');

        $this->assertSame('ribeiro', identity::navbar($viewer), 'Control: the viewer sees their own columns.');
        $this->assertSame(['Ana', 'ribeiro', 'RG 7'], identity::lines($viewer), 'The email address is the title.');

        $this->assertSame('Bruno Cardoso', identity::navbar($other));
        $this->assertSame('Bruno Cardoso', identity::title($other));
        $this->assertSame([], identity::lines($other));
    }

    /**
     * The vocabulary offers the types that keep a short string, and no other.
     *
     * @return void
     */
    public function test_the_vocabulary_offers_text_menu_and_social_fields_only(): void {
        $this->resetAfterTest();
        $this->field('rank');
        $this->field('post', 'menu', ['param1' => "Commander\nInstructor"]);
        $this->field('site', 'social', ['name' => 'url', 'param1' => 'url']);
        $this->field('agreed', 'checkbox');
        $this->field('joined', 'datetime');
        $this->field('story', 'textarea');

        $this->assertSame(
            [
                'fullname', 'alternatename', 'firstname', 'username', 'email', 'idnumber',
                'profile_field_rank', 'profile_field_post', 'profile_field_site',
            ],
            identity::fields()
        );
        $this->assertSame(identity::fields(), array_keys(identity::choices()));
        $this->assertSame('Profile field: Rank', identity::choices()['profile_field_rank']);
        $this->assertSame('Full name (site format)', identity::choices()['fullname']);

        // A field of another type is not shown even when a stored value names it.
        $user = $this->person(['firstname' => 'Ana', 'lastname' => 'Ribeiro', 'profile_field_agreed' => 1]);
        $this->assertSame(1, (int) $user->profile['agreed'], 'Precondition: the checkbox is in the record.');
        $this->set(identity::SETTING_NAVBAR, 'profile_field_agreed');
        $this->set(identity::SETTING_LINES, 'profile_field_agreed');
        $this->assertSame('Ana Ribeiro', identity::navbar($user));
        $this->assertSame([], identity::lines($user));

        // A social field whose network core does not know is left out: core's own field class warns on such a row
        // (so it comes last here, after every read of a person).
        $this->field('odd', 'social', ['name' => 'Not a network', 'param1' => 'url']);
        \cache::make(identity::COMPONENT, identity::CACHE)->purge();
        $this->assertNotContains('profile_field_odd', identity::fields());
        $this->assertContains('profile_field_site', identity::fields(), 'Control: a known network stays.');
    }

    /**
     * The definitions are read once, kept in the shared cache, and forgotten when a field or a category changes.
     *
     * @return void
     */
    public function test_the_definitions_are_cached_until_a_field_changes(): void {
        global $DB;
        $this->resetAfterTest();
        $rank = $this->field('rank');
        $user = $this->person(['firstname' => 'Ana', 'lastname' => 'Ribeiro', 'profile_field_rank' => 'Major']);
        $this->set(identity::SETTING_NAVBAR, 'profile_field_rank');
        $this->set(identity::SETTING_LINES, 'profile_field_rank');
        $this->set(identity::SETTING_TITLE, 'profile_field_rank');

        // The settings just written are read back from the table once; a page finds them in core's config cache.
        get_config(identity::COMPONENT, identity::SETTING_NAVBAR);
        $reads = $DB->perf_get_reads();
        $this->assertSame('Major', identity::navbar($user));
        $this->assertSame(1, $DB->perf_get_reads() - $reads, 'A cold cache costs one statement.');

        $reads = $DB->perf_get_reads();
        $this->assertSame('Major', identity::navbar($user));
        $this->assertSame('Major', identity::title($user));
        $this->assertSame([], identity::lines($user));
        $this->assertSame(0, $DB->perf_get_reads() - $reads, 'A warm cache costs none, the visibility check included.');
        $cached = \cache::make(identity::COMPONENT, identity::CACHE)->get(identity::CACHE);
        $this->assertSame(['rank'], array_keys($cached));
        $this->assertArrayNotHasKey('description', $cached['rank']);

        // The generator writes the table without core's events, so the cache still answers without the new field.
        $unit = $this->field('unit');
        $this->assertNotContains('profile_field_unit', identity::fields(), 'Precondition: the cache is in force.');
        \core\event\user_info_field_created::create_from_field($unit)->trigger();
        $this->assertContains('profile_field_unit', identity::fields());

        $DB->set_field('user_info_field', 'shortname', 'grade', ['id' => $rank->id]);
        $rank->shortname = 'grade';
        \core\event\user_info_field_updated::create_from_field($rank)->trigger();
        $this->assertSame(['profile_field_grade', 'profile_field_unit'], array_slice(identity::fields(), 6));

        $DB->delete_records('user_info_field', ['id' => $unit->id]);
        \core\event\user_info_field_deleted::create_from_field($unit)->trigger();
        $this->assertSame(['profile_field_grade'], array_slice(identity::fields(), 6));

        // The categories' order is the fields' order, so a category event empties the cache too.
        $category = self::getDataGenerator()->create_custom_profile_field_category(['name' => 'First', 'sortorder' => 0]);
        $this->field('first', 'text', ['categoryid' => $category->id]);
        $this->assertSame(['profile_field_grade'], array_slice(identity::fields(), 6), 'Precondition: still cached.');
        \core\event\user_info_category_created::create_from_category($category)->trigger();
        $this->assertSame(['profile_field_first', 'profile_field_grade'], array_slice(identity::fields(), 6));
    }

    /**
     * Under "log in as" the identity is the session's user: the account logged in as.
     *
     * @return void
     */
    public function test_under_log_in_as_the_identity_is_the_account_logged_in_as(): void {
        global $USER;
        $this->resetAfterTest();
        $this->field('rank');
        $sink = $this->redirectEvents();
        $student = self::getDataGenerator()->create_user(
            ['firstname' => 'Ana', 'lastname' => 'Ribeiro', 'alternatename' => 'Maj. Ribeiro', 'profile_field_rank' => 'Major']
        );
        $sink->close();
        $this->set(identity::SETTING_NAVBAR, 'alternatename');
        $this->set(identity::SETTING_TITLE, 'profile_field_rank');

        $this->setAdminUser();
        $admin = fullname($USER);
        $this->assertSame($admin, identity::navbar($USER), 'Control: the administrator has no alternate name.');

        \core\session\manager::loginas($student->id, \context_system::instance(), false);
        $this->assertTrue(\core\session\manager::is_loggedinas());
        $this->assertSame('Maj. Ribeiro', identity::navbar($USER));
        $this->assertSame('Major', identity::title($USER), 'Core loads the profile fields of the account logged in as.');
    }
}
