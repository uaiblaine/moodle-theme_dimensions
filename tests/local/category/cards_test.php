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
 * Theme Dimensions - Tests for the per-page bulk reads of the category showcase
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use core_course\customfield\course_handler;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\landingfields;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * Tests for the four per-page layers: one statement each, never one per course, and core's own choice of image.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(cards::class)]
final class cards_test extends \advanced_testcase {
    use fixture;

    /** @var string A valid 1x1 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /**
     * Put a file into a course's overview files area.
     *
     * @param int $courseid The course.
     * @param string $filename The file name.
     * @param string $content The bytes.
     * @return \stored_file The stored file, whose content hash the cover URL carries.
     */
    private function overview_file(int $courseid, string $filename, string $content): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \core\context\course::instance($courseid)->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
    }

    /**
     * The population rows of one category, for the current user.
     *
     * @param int $categoryid The category.
     * @return \stdClass[]
     */
    private function rows(int $categoryid): array {
        self::forget();
        $criteria = new criteria($categoryid);
        return population::records(population::scope($criteria), $criteria);
    }

    /**
     * Every layer costs the same for two cards and for twelve.
     */
    public function test_every_layer_is_bulk_not_per_course(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $CFG->courseoverviewfileslimit = 1;
        $CFG->courseoverviewfilesext = '.jpg,.gif,.png';
        $CFG->coursecontact = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $teacher = $generator->create_user();
        for ($i = 0; $i < 12; $i++) {
            $course = $generator->create_course(['category' => $category->id]);
            // A textarea value is the one that would cost: core's export runs the text filters per course context.
            course_handler::create()->instance_form_save((object) [
                'id' => $course->id,
                'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_EVENT,
                'customfield_' . landingfields::SHORTNAME_CITY => "City {$i}",
                'customfield_' . landingfields::SHORTNAME_PROGRAMME . '_editor' => [
                    'text' => "<p>Programme {$i}</p>", 'format' => FORMAT_HTML,
                ],
            ]);
            $this->overview_file((int) $course->id, 'card.png', base64_decode(self::PNG));
            $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        }
        $this->become($generator->create_user());
        $rows = $this->rows((int) $category->id);
        $this->assertCount(12, $rows);
        $two = array_slice($rows, 0, 2, true);
        $twelve = $rows;

        $layers = [
            'records' => fn(array $page) => cards::records(array_keys($page)),
            'values' => fn(array $page) => cards::values($page),
            'images' => fn(array $page) => cards::images($page),
            'contacts' => fn(array $page) => cards::contacts($page),
        ];
        foreach ($layers as $layer => $read) {
            // Warm the shared layers once (image info, course contacts), then meter a second request.
            $read($twelve);
            $small = $this->reads(fn() => $read($two));
            $large = $this->reads(fn() => $read($twelve));
            $this->assertSame($small, $large, "{$layer}: {$small} reads for two cards, {$large} for twelve.");
            $this->assertLessThanOrEqual(1, $large, "{$layer} costs at most one statement warm.");
        }

        $values = cards::values($two);
        foreach ($two as $courseid => $row) {
            $this->assertSame(landingfields::MODEL_EVENT, $values[(int) $courseid]->model);
            $this->assertStringStartsWith('City ', $values[(int) $courseid]->city);
            $this->assertStringContainsString('Programme ', $values[(int) $courseid]->programme);
        }
        // The card's textarea is the landing page's minus the filter pass: same text, same cleaning.
        $first = (int) array_key_first($two);
        $this->assertSame(landingfields::values($first)->programme, $values[$first]->programme, 'No filter changes this text.');
        $records = cards::records(array_keys($twelve));
        $this->assertSame(array_keys($twelve), array_keys($records), 'Rows come back in the order asked.');
        $this->assertObjectHasProperty('fullname', reset($records));
        $contacts = cards::contacts($two);
        foreach ($two as $courseid => $row) {
            $this->assertSame(fullname($teacher), $contacts[(int) $courseid][(int) $teacher->id]['fullname']);
            $this->assertNotSame('', $contacts[(int) $courseid][(int) $teacher->id]['rolename']);
        }
    }

    /**
     * The image is the one core would pick, and core's own cache is warmed with it.
     */
    public function test_images_reproduce_cores_choice_and_warm_the_core_cache(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->courseoverviewfileslimit = 1;
        $CFG->courseoverviewfilesext = '.jpg,.gif,.png';
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $png = base64_decode(self::PNG);

        $textfirst = $generator->create_course(['category' => $category->id]);
        $this->overview_file((int) $textfirst->id, 'a.txt', 'not an image');
        $this->overview_file((int) $textfirst->id, 'b.png', $png);
        $fake = $generator->create_course(['category' => $category->id]);
        $this->overview_file((int) $fake->id, 'a.png', 'not really a png');
        $none = $generator->create_course(['category' => $category->id]);
        $two = $generator->create_course(['category' => $category->id]);
        $this->overview_file((int) $two->id, 'z.png', $png);
        $this->overview_file((int) $two->id, 'm.png', $png);

        $this->become($generator->create_user());
        $rows = $this->rows((int) $category->id);
        $images = cards::images($rows);

        $expectedurl = function (int $courseid, string $filename): string {
            return \moodle_url::make_pluginfile_url(
                \core\context\course::instance($courseid)->id,
                'course',
                'overviewfiles',
                null,
                '/',
                $filename
            )->out(false);
        };
        $this->assertSame($expectedurl((int) $textfirst->id, 'b.png'), $images[(int) $textfirst->id]->out(false));
        $this->assertNull($images[(int) $fake->id], 'A file that is not an image is no image.');
        $this->assertNull($images[(int) $none->id]);
        $this->assertSame($expectedurl((int) $two->id, 'm.png'), $images[(int) $two->id]->out(false), 'Filename order.');

        // Core's cache now holds our answers without ever loading through its data source.
        $cache = \cache::make('core', 'course_image');
        $ours = [];
        foreach ($images as $courseid => $url) {
            $ours[$courseid] = $cache->get($courseid);
        }
        $cache->purge();
        foreach ($images as $courseid => $url) {
            $this->assertSame($cache->get($courseid), $ours[$courseid], "Core's own resolution agrees for course {$courseid}.");
        }
        $this->assertSame($images[(int) $two->id]->out_as_local_url(), $ours[(int) $two->id]);

        // With the limit at one, the second image of a course is out of reach, as it is for core.
        $CFG->courseoverviewfileslimit = 0;
        $this->assertSame([(int) $two->id => null], cards::images([(int) $two->id => $rows[(int) $two->id]]));
    }

    /**
     * The public shell mints the cover through the theme's route, and core's cache keeps core's URL.
     *
     * Core's course/overviewfiles route runs require_login() while the site forces login, so the
     * cover of a visitor's card loads for nobody: the public page asks for the theme's own route,
     * as the landing page's hero image already did. Two controls, and the test is worth nothing without
     * either: the logged-in call still gets core's URL, and core's course_image cache - read by
     * Boost Union's own cards and by the Dashboard, both behind the login - still holds core's URL
     * after the PUBLIC call, so the public page's route cannot leak onto a logged-in page.
     */
    public function test_the_public_shell_mints_the_cover_through_the_theme_route(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->courseoverviewfileslimit = 1;
        $CFG->courseoverviewfilesext = '.jpg,.gif,.png';
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $withcover = $generator->create_course(['category' => $category->id]);
        $file = $this->overview_file((int) $withcover->id, 'capa.png', base64_decode(self::PNG));
        $none = $generator->create_course(['category' => $category->id]);

        $this->become($generator->create_user());
        $rows = $this->rows((int) $category->id);
        $contextid = \core\context\course::instance($withcover->id)->id;
        $hash = $file->get_contenthash();

        /* The sentinel is what makes the leak guard below non-vacuous: core's course_image cache
           has a DATA SOURCE, so a miss would compute core's own URL and the assertion would pass
           against a public call that had written nothing at all. */
        $cache = \cache::make('core', 'course_image');
        $cache->set((int) $withcover->id, 'sentinel');

        $public = cards::images($rows, true);
        $this->assertStringEndsWith(
            "/pluginfile.php/{$contextid}/theme_dimensions/cover/{$withcover->id}/{$hash}/capa.png",
            $public[(int) $withcover->id]->out(false)
        );
        $this->assertNull($public[(int) $none->id], 'No image, no URL, in either mode.');

        // The leak guard: the public call overwrote the sentinel, and with CORE's own URL.
        $this->assertSame(
            "/pluginfile.php/{$contextid}/course/overviewfiles/capa.png",
            $cache->get((int) $withcover->id)
        );

        $internal = cards::images($rows);
        $this->assertStringEndsWith(
            "/pluginfile.php/{$contextid}/course/overviewfiles/capa.png",
            $internal[(int) $withcover->id]->out(false)
        );
        $this->assertStringNotContainsString(
            'theme_dimensions',
            $internal[(int) $withcover->id]->out(false),
            'Control: a reader with a session gets core\'s own route, the one its cache holds.'
        );
        $this->assertNull($internal[(int) $none->id]);
    }

    /**
     * The routes are asked of local_unlistedcourses only for the cards that print a relationship, and never otherwise.
     *
     * A page of open and enrolled cards pays nothing for the layer; a suspended card on the same kind of course gets
     * the route the plugin's batch reports. The suspended card is the control that the layer runs at all.
     */
    public function test_routes_are_asked_only_for_the_cards_that_print_a_relationship(): void {
        $this->resetAfterTest();
        $this->enable_enrol('self');
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $viewer = $generator->create_user();
        $open = $generator->create_course(['category' => $root->id]);
        $this->enable_instance((int) $open->id, 'self', ['customint6' => 1]);
        $enrolled = $generator->create_course(['category' => $root->id]);
        $generator->enrol_user($viewer->id, $enrolled->id);
        $suspended = $generator->create_course(['category' => $root->id]);
        $self = $this->enable_instance((int) $suspended->id, 'self', ['customint6' => 1]);
        $generator->enrol_user($viewer->id, $suspended->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);

        $this->become($viewer);
        $rows = population::resolve(new criteria((int) $root->id))->rows;
        $this->assertSame(classifier::STATE_SUSPENDED, $rows[(int) $suspended->id]->state, 'Precondition.');
        $plain = array_filter($rows, static fn(\stdClass $row): bool => $row->state !== classifier::STATE_SUSPENDED);
        $this->assertCount(2, $plain, 'Precondition: an open and an enrolled card.');

        $reads = $this->reads(function () use ($plain): void {
            $this->assertSame([], cards::next_actions($plain), 'No card prints a relationship: nothing is asked.');
        });
        $this->assertSame(0, $reads, 'And nothing is paid.');

        $answers = cards::next_actions($rows);
        $this->assertSame([(int) $suspended->id], array_keys($answers), 'Only the relationship card is asked about.');
        $this->assertSame([['kind' => 'self', 'instanceid' => (int) $self->id]], $answers[(int) $suspended->id]['routes']);
        $this->assertNull($answers[(int) $suspended->id]['opens'], 'A route open now: nothing to wait for.');
    }

    /**
     * A card with nothing on offer is asked about only where its course carries a window starting later.
     *
     * The batch dates the window that opens tomorrow; the same kind of card on a course with no such window is not
     * asked, and a page holding only that card pays nothing for the layer. The asked card is the control that the
     * layer runs at all for a card with no relationship.
     */
    public function test_a_card_with_nothing_on_offer_is_asked_only_where_a_window_opens_later(): void {
        $this->resetAfterTest();
        $this->enable_enrol('self');
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $viewer = $generator->create_user();
        $tomorrow = time() + DAYSECS;
        $ahead = $generator->create_course(['category' => $root->id]);
        $this->enable_instance((int) $ahead->id, 'self', ['customint6' => 1, 'enrolstartdate' => $tomorrow]);
        $shut = $generator->create_course(['category' => $root->id]);

        $this->become($viewer);
        $rows = population::resolve(new criteria((int) $root->id))->rows;
        $this->assertSame(classifier::STATE_NONE, $rows[(int) $ahead->id]->state, 'Precondition: nothing on offer yet.');
        $this->assertSame(classifier::STATE_NONE, $rows[(int) $shut->id]->state, 'Precondition: nor here.');

        $reads = $this->reads(function () use ($rows, $shut): void {
            $this->assertSame([], cards::next_actions([(int) $shut->id => $rows[(int) $shut->id]]), 'No window ahead.');
        });
        $this->assertSame(0, $reads, 'A page with no window ahead pays nothing.');

        $answers = cards::next_actions($rows);
        $this->assertSame([(int) $ahead->id], array_keys($answers), 'Only the card whose window opens later is asked.');
        $this->assertSame([], $answers[(int) $ahead->id]['routes']);
        $this->assertSame($tomorrow, $answers[(int) $ahead->id]['opens'], 'The plugin\'s date, as it gave it.');
    }

    /**
     * A conditional card's prerequisite is read once for the page, with its context, and only for such a card.
     */
    public function test_the_prerequisite_is_read_only_for_a_conditional_card(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $prerequisite = $generator->create_course(['fullname' => 'Base']);
        $conditional = $generator->create_course(['category' => $root->id]);
        $this->completed_instance($conditional, (int) $prerequisite->id);
        $plain = $generator->create_course(['category' => $root->id]);
        $viewer = $generator->create_user();
        $generator->enrol_user($viewer->id, $prerequisite->id);

        $this->become($viewer);
        $rows = population::resolve(new criteria((int) $root->id))->rows;
        $this->assertSame(classifier::STATE_CONDITIONAL, $rows[(int) $conditional->id]->state, 'Precondition.');

        $none = $this->reads(function () use ($rows, $plain): void {
            $this->assertSame([], cards::prerequisites([(int) $plain->id => $rows[(int) $plain->id]]));
        });
        $this->assertSame(0, $none, 'A page with no conditional card pays nothing.');
        $prerequisites = cards::prerequisites($rows);
        $this->assertSame([(int) $prerequisite->id], array_keys($prerequisites));
        $this->assertSame('Base', $prerequisites[(int) $prerequisite->id]->fullname);
    }

    /**
     * The values the card reads are the same whether the listing reads the card's fields only or every field.
     *
     * cards::values() hands values_many() the card's own fields (cards::CARD_SHORTNAMES). Over stored values,
     * stored empty values, courses with no row at all (two of them, so the shared default is shared) and a
     * programme whose DEFAULT text is not empty, every key the card reads must equal what the full read gives and
     * what a read of the course alone gives - the latter shares nothing. The keys compared are the ones
     * category_card's source reads, so a card that starts reading another value reddens this test until its field
     * is named. And the names are the card fields' alone: the other fields are not read at all.
     */
    public function test_the_card_reads_its_values_alike_with_its_fields_only(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        $programme = landingfields::get_field(landingfields::SHORTNAME_PROGRAMME);
        $config = (array) $programme->get('configdata');
        $config['defaultvalue'] = '<p>Default programme</p>';
        \core_customfield\api::save_field_configuration($programme, (object) ['configdata' => $config]);
        course_handler::reset_caches();
        landingfields::reset_field_cache();

        $generator = self::getDataGenerator();
        $handler = course_handler::create();
        $stored = (int) $generator->create_course()->id;
        $handler->instance_form_save((object) [
            'id' => $stored,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
            'customfield_' . landingfields::SHORTNAME_MODALITY => landingfields::MODALITY_INPERSON,
            'customfield_' . landingfields::SHORTNAME_WORKLOAD => 40,
            'customfield_' . landingfields::SHORTNAME_CITY => 'Rio & Co',
            'customfield_' . landingfields::SHORTNAME_VENUE => 'Sede',
            'customfield_' . landingfields::SHORTNAME_FORMAT => landingfields::FORMAT_TUTORED,
            'customfield_' . landingfields::SHORTNAME_PROGRAMME . '_editor' => ['text' => '<p>Um</p>', 'format' => FORMAT_HTML],
        ]);
        $empty = (int) $generator->create_course()->id;
        $handler->instance_form_save((object) [
            'id' => $empty,
            'customfield_' . landingfields::SHORTNAME_MODEL => 0,
            'customfield_' . landingfields::SHORTNAME_MODALITY => 0,
            'customfield_' . landingfields::SHORTNAME_CITY => '',
            'customfield_' . landingfields::SHORTNAME_PROGRAMME . '_editor' => ['text' => '', 'format' => FORMAT_HTML],
        ]);
        $bare = (int) $generator->create_course()->id;
        $alsobare = (int) $generator->create_course()->id;
        $other = (int) $generator->create_course()->id;
        $handler->instance_form_save((object) [
            'id' => $other,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_EVENT,
            'customfield_' . landingfields::SHORTNAME_WORKLOAD => 8,
            'customfield_' . landingfields::SHORTNAME_MODALITY => landingfields::MODALITY_ONLINE,
        ]);
        if (landingfields::picture_type_available()) {
            /** @var \core_customfield_generator $customfields */
            $customfields = $generator->get_plugin_generator('core_customfield');
            $data = $customfields->add_instance_data(landingfields::get_field(landingfields::SHORTNAME_CREST2), $stored, 1);
            get_file_storage()->create_file_from_pathname([
                'contextid' => \core\context\course::instance($stored)->id,
                'component' => 'customfield_picture',
                'filearea' => 'file',
                'itemid' => (int) $data->get('id'),
                'filepath' => '/',
                'filename' => 'crest.png',
            ], $CFG->dirroot . '/lib/tests/fixtures/gd-logo.png');
        }
        $ids = [$stored, $empty, $bare, $alsobare, $other];
        foreach ($ids as $courseid) {
            \core\context\course::instance($courseid);
        }

        // The keys category_card reads off its values, from its own source.
        preg_match_all('/\$this->values->([a-z]+)|\$values->([a-z]+)/', file_get_contents(
            __DIR__ . '/../../../classes/output/category_card.php'
        ), $matches);
        $read = array_values(array_unique(array_filter(array_merge($matches[1], $matches[2]))));
        sort($read);
        $compared = ['city', 'crests', 'modality', 'model', 'names', 'programme', 'venue', 'workload'];
        $this->assertSame($compared, $read, 'The card reads exactly the keys compared here.');

        $card = static function (\stdClass $values): array {
            $keys = [];
            foreach (['city', 'modality', 'model', 'programme', 'venue', 'workload'] as $key) {
                $keys[$key] = $values->$key;
            }
            $keys['crests'] = array_map(static fn(\stored_file $file): int => (int) $file->get_id(), $values->crests);
            $keys['names'] = array_intersect_key($values->names, array_flip(cards::CARD_SHORTNAMES));
            return $keys;
        };
        $only = landingfields::values_many($ids, false, cards::CARD_SHORTNAMES);
        $all = landingfields::values_many($ids, false);
        foreach ($ids as $courseid) {
            $alone = landingfields::values_many([$courseid], false);
            $this->assertSame($card($alone[$courseid]), $card($only[$courseid]), "Course {$courseid}, against itself alone.");
            $this->assertSame($card($all[$courseid]), $card($only[$courseid]), "Course {$courseid}, against every field.");
            $this->assertSame(
                [],
                array_diff(array_keys($only[$courseid]->names), cards::CARD_SHORTNAMES),
                'Only the card\'s fields are read.'
            );
        }
        $this->assertSame(
            landingfields::values_many([$bare], false)[$bare]->programme,
            $only[$alsobare]->programme,
            'The shared default reads the default text, formatted as the course alone formats it.'
        );
        $this->assertStringContainsString('Default programme', (string) $only[$alsobare]->programme, 'Precondition.');
        $this->assertSame('Rio & Co', $only[$stored]->city);
        $this->assertSame(40.0, $only[$stored]->workload);
        $this->assertSame(landingfields::FORMAT_TUTORED, $all[$stored]->format, 'Control: the full read has the format.');
        $this->assertSame(0, $only[$stored]->format, 'A field the card does not read keeps its empty value.');
    }

    /**
     * Core's course_image cache is written where its entry is missing or says otherwise, and only there.
     *
     * The cache is compared first, without its data source; a second page of the same courses finds every entry
     * right and writes nothing. The writes are counted by MUC's own statistics, which perfdebug switches on.
     */
    public function test_the_image_cache_is_written_only_where_it_is_missing_or_wrong(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->courseoverviewfileslimit = 1;
        $CFG->courseoverviewfilesext = '.jpg,.gif,.png';
        $CFG->perfdebug = 15;
        \core_cache\factory::reset();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $covered = $generator->create_course(['category' => $category->id]);
        $this->overview_file((int) $covered->id, 'capa.png', base64_decode(self::PNG));
        $plain = $generator->create_course(['category' => $category->id]);
        $third = $generator->create_course(['category' => $category->id]);
        $this->overview_file((int) $third->id, 'capa.png', base64_decode(self::PNG));

        $this->become($generator->create_user());
        $rows = $this->rows((int) $category->id);
        $sets = static function (): int {
            $count = 0;
            foreach (\core_cache\helper::get_stats()['core/course_image']['stores'] ?? [] as $store) {
                $count += (int) $store['sets'];
            }
            return $count;
        };
        $cache = \cache::make('core', 'course_image');
        $cache->purge();

        $before = $sets();
        $images = cards::images($rows);
        $this->assertSame(3, $sets() - $before, 'A cold cache: every course is written.');
        $contextid = \core\context\course::instance($covered->id)->id;
        $this->assertSame("/pluginfile.php/{$contextid}/course/overviewfiles/capa.png", $cache->get((int) $covered->id));
        $this->assertNull($cache->get((int) $plain->id), 'No image is cached as null, as core caches it.');

        $before = $sets();
        $this->assertEquals($images, cards::images($rows));
        $this->assertSame(0, $sets() - $before, 'A warm cache that is right: nothing is written.');

        $cache->set((int) $third->id, 'stale');
        $cache->delete((int) $plain->id);
        $before = $sets();
        cards::images($rows);
        $this->assertSame(2, $sets() - $before, 'The wrong entry and the missing one, and nothing else.');
        $this->assertSame($images[(int) $third->id]->out_as_local_url(), $cache->get((int) $third->id));
    }
}
