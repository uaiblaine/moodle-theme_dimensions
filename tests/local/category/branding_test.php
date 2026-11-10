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
 * Theme Dimensions - Tests of the category branding: the row, the cache, the image route
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use local_unlistedcourses\category_discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use theme_dimensions\event\category_branding_updated;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * Tests of the category branding.
 *
 * The route tests follow publicfiles_test: every refusal beside a control that is served in
 * the same run, so an assertion cannot pass against a route that refuses everything.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(branding::class)]
#[CoversClass(category_branding_updated::class)]
final class branding_test extends \advanced_testcase {
    use fixture;

    /** @var string A valid 1x1 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /**
     * Store a file in a category's hero area, as the editor's file manager would.
     *
     * @param int $categoryid The category.
     * @param string $filename The file name.
     * @param string $content The bytes.
     * @return \stored_file
     */
    private static function store(int $categoryid, string $filename, string $content): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \core\context\coursecat::instance($categoryid)->id,
            'component' => branding::COMPONENT,
            'filearea' => branding::AREA_HERO,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
    }

    /**
     * Store a file in one of a category's badge areas, as the editor's file manager would.
     *
     * @param int $categoryid The category.
     * @param string $area One of branding::AREA_BADGES.
     * @param string $filename The file name.
     * @param string $content The bytes.
     * @return \stored_file
     */
    private static function store_badge(int $categoryid, string $area, string $filename, string $content): \stored_file {
        return self::store_area($categoryid, $area, $filename, $content);
    }

    /**
     * Store a file in any single-file area of a category, whatever the editor would have used.
     *
     * @param int $categoryid The category.
     * @param string $area The area.
     * @param string $filename The file name.
     * @param string $content The bytes.
     * @return \stored_file
     */
    private static function store_area(int $categoryid, string $area, string $filename, string $content): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \core\context\coursecat::instance($categoryid)->id,
            'component' => branding::COMPONENT,
            'filearea' => $area,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
    }

    /**
     * The bytes of one of the two real videos these tests ship.
     *
     * Real files and not hand-made headers: cover.mp4 is core's
     * lib/editor/tiny/tests/behat/fixtures/moodle-logo.mp4 and cover.webm is Moodle 4.5's
     * lib/editor/atto/tests/fixtures/moodle-logo.webm. A signature check written against bytes
     * the same code invented would pass against a rule that reads the wrong offset.
     *
     * @param string $name The file name under tests/fixtures.
     * @return string The bytes.
     */
    private static function video_bytes(string $name): string {
        return (string) file_get_contents(__DIR__ . '/../../fixtures/' . $name);
    }

    /**
     * A draft area holding one file, the way a submitted file manager hands one over.
     *
     * @param string $filename The file name.
     * @param string $content The bytes.
     * @return int The draft item id.
     */
    private static function draft_with(string $filename, string $content): int {
        global $USER;

        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \core\context\user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
        return $draftitemid;
    }

    /**
     * A draft area holding ONE file that is an alias of a file in the user's private area.
     *
     * The shape a manager produces by choosing "create an alias" in a file picker. The private
     * files repository is the one every Moodle site has and the one core's own reference tests
     * use.
     *
     * @param string $filename The alias's name in the draft.
     * @return int The draft item id.
     */
    private static function draft_with_alias(string $filename): int {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/repository/lib.php');
        $fs = get_file_storage();
        $usercontext = \core\context\user::instance($USER->id);
        $source = [
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'private',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'source-' . $filename,
        ];
        $fs->create_file_from_string($source, base64_decode(self::PNG));
        $repositories = \repository::get_instances(['type' => 'user']);
        $repository = reset($repositories);
        \PHPUnit\Framework\Assert::assertNotFalse($repository, 'Precondition: the private files repository exists.');
        $draftid = file_get_unused_draft_itemid();
        $alias = $fs->create_file_from_reference([
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftid,
            'filepath' => '/',
            'filename' => $filename,
        ], $repository->id, \file_storage::pack_reference($source));
        \PHPUnit\Framework\Assert::assertTrue($alias->is_external_file(), 'Precondition: the draft really holds an alias.');
        return $draftid;
    }

    /**
     * The path segments of a hero URL after the area.
     *
     * @param int $categoryid The category.
     * @param \stored_file $file The file.
     * @return string[]
     */
    private static function args(int $categoryid, \stored_file $file): array {
        return [(string) $categoryid, $file->get_contenthash(), $file->get_filename()];
    }

    /**
     * A category nobody branded reads the defaults, and its cached payload names no image.
     */
    public function test_a_category_nobody_branded_reads_the_defaults(): void {
        $this->resetAfterTest();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        $row = branding::record($categoryid);
        $this->assertSame(0, (int) $row->id);
        $this->assertSame(branding::DEFAULT_LAYOUT, $row->layout);
        $this->assertSame(branding::DEFAULT_BG, $row->bgcolour);
        $this->assertSame(branding::DEFAULT_INK, $row->inkcolour);
        $this->assertSame(branding::DEFAULT_OVERLAY, $row->overlay);
        $this->assertSame('', $row->tagline);
        $this->assertSame('', $row->publicsummary);

        $payload = branding::for_category($categoryid);
        $this->assertNull($payload['image']);
        $this->assertSame('', $payload['tagline']);
        $this->assertSame('', $payload['publicsummary']);
        $this->assertSame(branding::DEFAULT_LAYOUT, $payload['layout']);
    }

    /**
     * A save writes one row per category, keeps the values in their vocabulary, and fires the event.
     */
    public function test_save_writes_one_row_per_category_and_fires_the_event(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        $sink = $this->redirectEvents();
        $row = branding::save($categoryid, (object) [
            'layout' => branding::LAYOUT_IMAGE,
            'bgcolour' => '#12484F',
            'inkcolour' => '#FFF',
            'overlay' => 60,
            'tagline' => '  Formação continuada & eventos  ',
            'publicsummary' => '  Cursos e eventos abertos à organização  ',
            'publicdescription_editor' => ['text' => '<p>Público</p>', 'format' => FORMAT_HTML],
        ]);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(category_branding_updated::class, $events[0]);
        $this->assertSame((int) $row->id, (int) $events[0]->objectid);
        $this->assertSame($categoryid, (int) $events[0]->contextinstanceid);
        $this->assertStringContainsString('categoryhero.php?id=' . $categoryid, $events[0]->get_url()->out(false));
        $this->assertSame('Formação continuada & eventos', $row->tagline, 'Trimmed, in the plain spelling.');
        $this->assertSame(branding::LAYOUT_IMAGE, $row->layout);
        $this->assertSame('#12484f', $row->bgcolour, 'Stored in the one spelling, whatever case was typed.');
        $this->assertSame('#ffffff', $row->inkcolour, 'The three-digit form is expanded on the way in.');
        $this->assertSame(60, $row->overlay);
        $this->assertSame('<p>Público</p>', $row->publicdescription);
        $this->assertSame((int) FORMAT_HTML, $row->publicdescriptionformat);
        $this->assertSame(
            'Cursos e eventos abertos à organização',
            $row->publicsummary,
            'The public description is trimmed on the way in, so a field of spaces is no description.'
        );

        // A second save updates the same row rather than adding one.
        $again = branding::save($categoryid, (object) [
            'layout' => branding::LAYOUT_COMPACT,
            'bgcolour' => '#3d2a5e',
            'inkcolour' => '#ffffff',
            'overlay' => 90,
            'tagline' => 'Outra',
        ]);
        $this->assertSame((int) $row->id, (int) $again->id);
        $this->assertSame(1, $DB->count_records(branding::TABLE, ['categoryid' => $categoryid]));
        $this->assertSame(branding::LAYOUT_COMPACT, $again->layout);
        $this->assertSame('', $again->publicdescription, 'Absent from the submission means empty.');
        $this->assertSame('', $again->publicsummary, 'Absent from the submission means empty.');
    }

    /**
     * A value outside its vocabulary is refused before anything is written.
     */
    public function test_save_refuses_a_value_outside_the_vocabulary(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        /* Each case carries the reason it is refused for, and the message is asserted against it:
           with a guard removed from validate(), the value reaches a later check that refuses it
           for another reason, and a test reading only the exception would still pass. */
        foreach (
            [
                [['bgcolour' => 'crimson'], 'background colour is not a hex colour'],
                [['bgcolour' => '#fff;background:url(x)'], 'background colour is not a hex colour'],
                [['inkcolour' => '#12'], 'text colour is not a hex colour'],
                // A pair measuring 3.90:1 - under the AA floor of 4.5:1, and refused for it.
                [['bgcolour' => '#818181', 'inkcolour' => '#ffffff'], 'under the 4.5:1 minimum'],
                [['layout' => 'banner'], 'Unknown hero layout'],
                [['overlay' => 45], 'Unknown hero overlay'],
                /* 0 (transparent) is in the vocabulary, so every spelling a cast would turn into 0
                   has to be refused by name, or "not an overlay" silently becomes "no colour at
                   all". An absent overlay (null included, which ?? reads as absent) keeps meaning
                   the default - every save in this file that omits the overlay relies on it - which
                   is why these spellings are listed one by one rather than refused wholesale. */
                [['overlay' => ''], 'Unknown hero overlay'],
                [['overlay' => 'transparent'], 'Unknown hero overlay'],
                [['overlay' => '78.5'], 'Unknown hero overlay'],
                [['tagline' => str_repeat('a', branding::TAGLINE_MAXLENGTH + 1)], 'tagline is longer'],
                [
                    ['publicsummary' => str_repeat('a', branding::PUBLICSUMMARY_MAXLENGTH + 1)],
                    'public description is longer',
                ],
            ] as [$bad, $because]
        ) {
            try {
                branding::save($categoryid, (object) $bad);
                $this->fail('Accepted ' . json_encode($bad));
            } catch (\invalid_parameter_exception $e) {
                $this->assertStringContainsString($because, $e->getMessage(), json_encode($bad));
                $this->assertSame(0, $DB->count_records(branding::TABLE, ['categoryid' => $categoryid]));
            }
        }
    }

    /**
     * The transparent overlay is stored as 0 and read back as 0, not as the default.
     *
     * The control beside the refusals above: every spelling a cast would map onto 0 is refused,
     * and the one value that really is 0 goes in and comes back. Without this the refusals could
     * also refuse a real choice and every other test would stay green.
     */
    public function test_the_transparent_overlay_is_stored_and_read_back(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        $row = branding::save($categoryid, (object) ['layout' => branding::LAYOUT_IMAGE, 'overlay' => 0]);
        $this->assertSame(0, $row->overlay);
        $this->assertSame(0, branding::record($categoryid)->overlay);
        $this->assertSame(0, branding::for_category($categoryid)['overlay']);
        $this->assertNotSame(
            branding::DEFAULT_OVERLAY,
            $row->overlay,
            'Control: the default is a different number, so reading 0 back means something.'
        );

        // And the form's own submitted spelling - a select posts a string - reaches the same 0.
        $fromform = branding::save($categoryid, (object) ['layout' => branding::LAYOUT_IMAGE, 'overlay' => '0']);
        $this->assertSame(0, $fromform->overlay);
    }

    /**
     * A stored value a later version retired reads as the default rather than failing the page.
     */
    public function test_a_stored_value_outside_the_vocabulary_reads_as_the_default(): void {
        global $DB;
        $this->resetAfterTest();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $DB->insert_record(branding::TABLE, (object) [
            'categoryid' => $categoryid,
            'layout' => 'banner',
            // Both fit the column and neither is a hex colour: the value a manual write, a
            // migration or a restore from another site could leave behind.
            'bgcolour' => '#12345',
            'inkcolour' => 'white',
            'overlay' => 45,
            'tagline' => 'Kept',
            'publicdescriptionformat' => FORMAT_HTML,
        ]);

        $row = branding::record($categoryid);
        $this->assertSame(branding::DEFAULT_LAYOUT, $row->layout);
        $this->assertSame(branding::DEFAULT_BG, $row->bgcolour);
        $this->assertSame(branding::DEFAULT_INK, $row->inkcolour);
        $this->assertSame(branding::DEFAULT_OVERLAY, $row->overlay);
        $this->assertSame('Kept', $row->tagline);

        /* And again on the way out: the cached payload is what the hero's inline style is built
           from, so a row a migration or a direct write left poisoned must read as the default
           there too, not only through record(). */
        $payload = branding::for_category($categoryid);
        $this->assertSame(branding::DEFAULT_BG, $payload['bgcolour']);
        $this->assertSame(branding::DEFAULT_INK, $payload['inkcolour']);
    }

    /**
     * The cached payload holds exactly the branding keys - no count, no course name - and a save drops it.
     *
     * The keys are the whole contract of the one thing the showcase caches across requests
     * for every viewer; whatever is added to it is served to everyone, which is why the list
     * is pinned rather than the presence of a few known keys.
     */
    public function test_the_cached_payload_holds_exactly_the_branding_keys(): void {
        $this->resetAfterTest();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $file = self::store($categoryid, 'hero.png', base64_decode(self::PNG));
        $badge = self::store_badge($categoryid, branding::AREA_BADGES[0], 'crest.png', base64_decode(self::PNG));

        $payload = branding::for_category($categoryid);
        $this->assertSame(branding::CACHED_KEYS, array_keys($payload));
        $cached = \core_cache\cache::make(branding::COMPONENT, branding::CACHE)->get($categoryid);
        $this->assertIsArray($cached, 'The payload is cached.');
        $this->assertSame(branding::CACHED_KEYS, array_keys($cached), 'The cache holds the payload and nothing more.');
        $this->assertSame($file->get_contenthash(), $payload['image']['contenthash']);
        $this->assertSame('hero.png', $payload['image']['filename']);
        $this->assertSame($badge->get_contenthash(), $payload['badges'][branding::AREA_BADGES[0]]['contenthash']);
        $this->assertSame(
            $payload['badges'],
            $cached['badges'],
            'The crests are cached with the rest: a hash and a file name are properties of the category.'
        );
        foreach (branding::CACHED_KEYS as $key) {
            $this->assertDoesNotMatchRegularExpression('/count|total|course|viewer|user/', $key);
        }

        // A save drops the entry: the next read sees the change.
        $this->setAdminUser();
        branding::save($categoryid, (object) ['tagline' => 'Nova']);
        $this->assertSame('Nova', branding::for_category($categoryid)['tagline']);
    }

    /**
     * The hero URL carries the category id, the content hash and the file name, through the theme's route.
     */
    public function test_the_image_url_carries_the_category_the_hash_and_the_name(): void {
        $this->resetAfterTest();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $file = self::store($categoryid, 'hero.png', base64_decode(self::PNG));
        $contextid = \core\context\coursecat::instance($categoryid)->id;

        $url = branding::image_url($categoryid, branding::for_category($categoryid)['image'])->out(false);

        $this->assertStringEndsWith(
            "/pluginfile.php/{$contextid}/theme_dimensions/categoryhero/{$categoryid}/"
                . $file->get_contenthash() . '/hero.png',
            $url
        );
    }

    /**
     * The site-wide fallback follows the theme setting: its own file in the system context, or nothing.
     */
    public function test_the_fallback_url_follows_the_theme_setting(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->assertNull(branding::fallback_url(), 'No setting, no URL.');

        $syscontext = \core\context\system::instance();
        get_file_storage()->create_file_from_string([
            'contextid' => $syscontext->id,
            'component' => branding::COMPONENT,
            'filearea' => branding::AREA_FALLBACK,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'fallback.png',
        ], base64_decode(self::PNG));
        set_config(branding::SETTING_FALLBACK, '/fallback.png', 'theme_dimensions');
        \core\output\theme_config::load('dimensions');

        $url = branding::fallback_url();
        $this->assertNotNull($url);
        // Absolute, with the site's own scheme and ONE host: core hands the address back
        // protocol-relative, and a core\url built from that spelling doubles the host.
        $this->assertStringStartsWith(
            "{$CFG->wwwroot}/pluginfile.php/{$syscontext->id}/theme_dimensions/categoryherofallback/",
            $url
        );
        $this->assertStringEndsWith('/fallback.png', $url);
        $this->assertSame(1, substr_count($url, parse_url($CFG->wwwroot, PHP_URL_HOST)));
    }

    /**
     * Deleting the category drops the branding row; core drops the files with the context.
     *
     * The files are the control that the deletion ran at all; the row is what this theme's
     * observer is for.
     */
    public function test_deleting_the_category_drops_the_row_and_the_cache(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        branding::save($categoryid, (object) ['tagline' => 'Adeus']);
        self::store($categoryid, 'hero.png', base64_decode(self::PNG));
        $contextid = \core\context\coursecat::instance($categoryid)->id;
        $fs = get_file_storage();
        $cache = \core_cache\cache::make(branding::COMPONENT, branding::CACHE);

        self::store_badge($categoryid, branding::AREA_BADGES[0], 'crest.png', base64_decode(self::PNG));

        $this->assertSame(1, $DB->count_records(branding::TABLE, ['categoryid' => $categoryid]), 'Precondition.');
        $this->assertNotEmpty($fs->get_area_files($contextid, branding::COMPONENT, branding::AREA_HERO, 0, 'id', false));
        $this->assertNotEmpty(
            $fs->get_area_files($contextid, branding::COMPONENT, branding::AREA_BADGES[0], 0, 'id', false),
            'Precondition: there is a crest to leave behind.'
        );
        $this->assertSame('Adeus', branding::for_category($categoryid)['tagline'], 'Precondition: the cache is warm.');
        $this->assertIsArray($cache->get($categoryid));

        \core_course_category::get($categoryid)->delete_full(false);

        $this->assertSame(0, $DB->count_records(branding::TABLE, ['categoryid' => $categoryid]));
        $this->assertFalse($cache->get($categoryid));
        $this->assertEmpty($fs->get_area_files($contextid, branding::COMPONENT, branding::AREA_HERO, 0, 'id', false));
        /* And the crests go with it, though this plugin deletes no file: core deletes the
           category's context, and every file in it, before the event that drops the row fires. */
        foreach (branding::AREA_BADGES as $area) {
            $this->assertEmpty(
                $fs->get_area_files($contextid, branding::COMPONENT, $area, 0, 'id', false),
                'A deleted category leaves no badge file behind: ' . $area
            );
        }
    }

    /**
     * The route serves the current image of a category the viewer may list, and nothing under another name.
     */
    public function test_the_route_serves_the_image_of_a_category_the_viewer_may_list(): void {
        $this->resetAfterTest();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $file = self::store($categoryid, 'hero.png', base64_decode(self::PNG));
        $context = \core\context\coursecat::instance($categoryid);
        $this->become(self::getDataGenerator()->create_user());

        $found = branding::resolve($context, self::args($categoryid, $file));
        $this->assertSame($file->get_contenthash(), $found?->get_contenthash());

        $this->assertNull(branding::resolve($context, [(string) $categoryid, sha1('stale'), 'hero.png']));
        $this->assertNull(branding::resolve($context, [(string) $categoryid, $file->get_contenthash(), 'other.png']));
        $this->assertNull(branding::resolve($context, [(string) $categoryid, $file->get_contenthash()]));
        $this->assertNull(branding::resolve($context, [(string) $categoryid]));
    }

    /**
     * The route re-asks both gates for the viewer, core's first: a hidden category and an unlisted one refuse a stranger.
     */
    public function test_the_route_rechecks_the_category_for_the_viewer(): void {
        global $CFG;
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $unlisted = (int) $generator->create_category()->id;
        $hidden = (int) $generator->create_category(['visible' => 0])->id;
        $listed = (int) $generator->create_category()->id;
        $unlistedfile = self::store($unlisted, 'hero.png', base64_decode(self::PNG));
        $hiddenfile = self::store($hidden, 'hero.png', base64_decode(self::PNG));
        $listedfile = self::store($listed, 'hero.png', base64_decode(self::PNG));
        $this->set_category_state($unlisted, category_discoverability::STATE_UNLISTED);
        $stranger = $generator->create_user();

        $this->become($stranger);
        $unlistedcontext = \core\context\coursecat::instance($unlisted);
        $hiddencontext = \core\context\coursecat::instance($hidden);
        $listedcontext = \core\context\coursecat::instance($listed);
        $unlistedargs = self::args($unlisted, $unlistedfile);
        $hiddenargs = self::args($hidden, $hiddenfile);
        $listedargs = self::args($listed, $listedfile);
        $this->assertNull(branding::resolve($unlistedcontext, $unlistedargs), 'The plugin withholds it.');
        $this->assertNull(branding::resolve($hiddencontext, $hiddenargs), 'Core withholds it.');
        $this->assertNotNull(branding::resolve($listedcontext, $listedargs), 'Control: the listed one.');

        $this->setAdminUser();
        self::forget();
        $this->assertNotNull(branding::resolve($unlistedcontext, $unlistedargs), 'Control: the administrator.');
        $this->assertNotNull(branding::resolve($hiddencontext, $hiddenargs), 'Control: the administrator.');

        // A visitor under forcelogin holds no capability: core refuses them here as it refuses them the page.
        $CFG->forcelogin = 1;
        $this->become(null);
        $this->assertNull(branding::resolve($listedcontext, $listedargs));

        // A category that does not exist is refused, whoever asks.
        $this->setAdminUser();
        self::forget();
        $missing = [(string) ($listed + 1000), $listedfile->get_contenthash(), 'hero.png'];
        $this->assertNull(branding::resolve($listedcontext, $missing));
    }

    /**
     * The route refuses a category id that is not the context's own instance.
     */
    public function test_the_route_refuses_a_category_id_that_is_not_the_contexts_own(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $a = (int) $generator->create_category()->id;
        $b = (int) $generator->create_category()->id;
        $filea = self::store($a, 'hero.png', base64_decode(self::PNG));
        $this->setAdminUser();

        // A's id and file under B's context: refused, though the viewer may list both.
        $this->assertNull(branding::resolve(\core\context\coursecat::instance($b), self::args($a, $filea)));
        $this->assertNotNull(branding::resolve(\core\context\coursecat::instance($a), self::args($a, $filea)), 'Control.');
    }

    /**
     * The route refuses a context that is not a category's, even one whose instance id is the category's.
     */
    public function test_the_route_refuses_a_context_that_is_not_a_categorys(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $generator->create_course();
        $courseid = (int) $generator->create_course()->id;
        $this->assertFalse($DB->record_exists('course_categories', ['id' => $courseid]), 'Precondition: the id is free.');

        // A category inserted under the course's own id, so that only the context level can refuse it.
        // insert_record() drops a given id; only insert_record_raw() honours one, as restore does.
        $DB->insert_record_raw('course_categories', [
            'id' => $courseid,
            'name' => 'Twin',
            'parent' => 0,
            'depth' => 1,
            'path' => '/' . $courseid,
            'sortorder' => 0,
            'visible' => 1,
            'timemodified' => time(),
        ], false, false, true);
        \core_cache\helper::purge_by_event('changesincoursecat');
        // The raw insert leaves the sequence behind on PostgreSQL; a later create_category() would collide.
        $DB->get_manager()->reset_sequence('course_categories');
        $file = self::store($courseid, 'hero.png', base64_decode(self::PNG));
        $categorycontext = \core\context\coursecat::instance($courseid);
        $coursecontext = \core\context\course::instance($courseid);
        $this->assertSame((int) $coursecontext->instanceid, (int) $categorycontext->instanceid, 'Precondition: twins.');

        $this->assertNotNull(branding::resolve($categorycontext, self::args($courseid, $file)), 'Control: its own context.');
        $this->assertNull(branding::resolve($coursecontext, self::args($courseid, $file)));
    }

    /**
     * The route serves images only, and the hero links nothing else.
     *
     * A same-origin .html from a file area would be script for everyone who can reach it.
     */
    public function test_the_route_serves_images_only(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $html = self::store($categoryid, 'hero.html', '<script>alert(1)</script>');
        $context = \core\context\coursecat::instance($categoryid);

        $this->assertNull(branding::resolve($context, self::args($categoryid, $html)));
        $this->assertNull(branding::for_category($categoryid)['image'], 'And the hero links nothing.');
    }

    /**
     * The route exists under the name file_pluginfile() dispatches to, beside the landing page's.
     */
    public function test_the_route_is_wired_into_core_file_serving(): void {
        global $CFG;
        require_once($CFG->dirroot . '/theme/dimensions/lib.php');
        $this->assertTrue(function_exists('theme_dimensions_pluginfile'));
        $source = file_get_contents($CFG->dirroot . '/theme/dimensions/lib.php');
        $this->assertStringContainsString('$branding::serve($context, $filearea, $args)', $source);
        $this->assertStringContainsString('$branding::AREA_OGIMAGE', $source, 'The og area reaches the same route.');
        $this->assertStringContainsString('publicfiles::serve($context, $filearea, $args)', $source);
    }

    /**
     * Every image answer is private, a visitor's included.
     *
     * The URL carries only the content hash, which does not change when a category stops being
     * public, so a shared proxy holding a public answer would keep serving the image for the whole
     * hashed lifetime. The serve path is read as source: a request that reaches send_file() ends
     * the process, and the options are the whole of the decision.
     */
    public function test_the_image_is_always_sent_private(): void {
        global $CFG;
        $source = file_get_contents($CFG->dirroot . '/theme/dimensions/classes/local/category/branding.php');
        $this->assertStringContainsString("\$options = ['cacheability' => 'private'];", $source);
        $this->assertSame(
            0,
            preg_match("/'cacheability'\\s*=>\\s*[^']*'public'/", $source),
            'No branch of the route may ask for a public answer.'
        );
        // The control: the pattern above does see a public option when one is written.
        $this->assertSame(1, preg_match(
            "/'cacheability'\\s*=>\\s*[^']*'public'/",
            "\$options = ['cacheability' => \$visitor ? 'public' : 'private'];"
        ));
    }

    /**
     * Store a JPEG GD can read, so that the Open Graph derivative can really be made from it.
     *
     * The 1x1 PNG above is enough for everything the hero route does with bytes; the derivative
     * decodes, scales and re-encodes, so it gets a source with real dimensions.
     *
     * @param int $contextid The context to store it in.
     * @param string $filearea The area.
     * @param string $filename The file name.
     * @return \stored_file
     */
    private static function store_jpeg(int $contextid, string $filearea, string $filename): \stored_file {
        $image = imagecreatetruecolor(1600, 900);
        ob_start();
        imagejpeg($image);
        imagedestroy($image);
        return get_file_storage()->create_file_from_string([
            'contextid' => $contextid,
            'component' => branding::COMPONENT,
            'filearea' => $filearea,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
        ], (string) ob_get_clean());
    }

    /**
     * A visitor is served the hero of a public category and of no other.
     *
     * The public category is the control in every half of this test: without it an assertion
     * here would pass against a route that refuses every visitor.
     *
     * @return void
     */
    public function test_the_route_serves_a_visitor_the_hero_of_a_public_category_only(): void {
        global $CFG;
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $public = (int) $generator->create_category()->id;
        $listed = (int) $generator->create_category()->id;
        $unlisted = (int) $generator->create_category()->id;
        $publicfile = self::store($public, 'hero.png', base64_decode(self::PNG));
        $listedfile = self::store($listed, 'hero.png', base64_decode(self::PNG));
        $unlistedfile = self::store($unlisted, 'hero.png', base64_decode(self::PNG));
        $this->set_category_state($public, category_discoverability::STATE_PUBLIC);
        $this->set_category_state($unlisted, category_discoverability::STATE_UNLISTED);

        $CFG->forcelogin = 1;
        $this->become(null);
        $publiccontext = \core\context\coursecat::instance($public);
        $listedcontext = \core\context\coursecat::instance($listed);
        $unlistedcontext = \core\context\coursecat::instance($unlisted);

        $this->assertNotNull(
            branding::resolve($publiccontext, self::args($public, $publicfile)),
            'Control: the public category is served to a visitor under forcelogin.'
        );
        $this->assertNull(branding::resolve($listedcontext, self::args($listed, $listedfile)), 'A listed one is not.');
        $this->assertNull(branding::resolve($unlistedcontext, self::args($unlisted, $unlistedfile)), 'Nor an unlisted one.');

        // The site's switch closes this route with the page: the state stays, the serving stops.
        set_config(publicaccess::SETTING_ENABLED, '0', branding::COMPONENT);
        $this->assertNull(branding::resolve($publiccontext, self::args($public, $publicfile)));
        set_config(publicaccess::SETTING_ENABLED, '1', branding::COMPONENT);
        $this->assertNotNull(
            branding::resolve($publiccontext, self::args($public, $publicfile)),
            'Control: switched back on, the same request is served again.'
        );

        // And a logged-in requester still goes through the viewer's own gate, not the public one.
        $this->become($generator->create_user());
        $this->assertNotNull(branding::resolve($listedcontext, self::args($listed, $listedfile)));
        $this->assertNull(branding::resolve($unlistedcontext, self::args($unlisted, $unlistedfile)));
    }

    /**
     * The og area serves the derivative's source, under its own name and the current content only.
     *
     * @return void
     */
    public function test_the_og_area_serves_the_current_source_under_its_own_name(): void {
        $this->resetAfterTest();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $context = \core\context\coursecat::instance($categoryid);
        $file = self::store_jpeg($context->id, branding::AREA_HERO, 'hero.jpg');
        $hash = $file->get_contenthash();
        $this->setAdminUser();

        $found = branding::resolve($context, [(string) $categoryid, $hash, branding::OG_FILENAME], branding::AREA_OGIMAGE);
        $this->assertSame($hash, $found?->get_contenthash(), 'Control: the current content under og.jpg.');

        $stale = [(string) $categoryid, sha1('stale'), branding::OG_FILENAME];
        $this->assertNull(branding::resolve($context, $stale, branding::AREA_OGIMAGE), 'A stale hash is refused.');
        $wrongname = [(string) $categoryid, $hash, 'hero.jpg'];
        $this->assertNull(branding::resolve($context, $wrongname, branding::AREA_OGIMAGE), 'And so is another name.');

        // An area this route knows nothing about is refused whatever the path says.
        $this->assertNull(branding::resolve($context, self::args($categoryid, $file), 'categorysomethingelse'));
        $this->assertNotNull(branding::resolve($context, self::args($categoryid, $file)), 'Control: the hero area.');
    }

    /**
     * Without an image of its own a category falls back to the site-wide one, under its OWN address.
     *
     * @return void
     */
    public function test_the_og_source_falls_back_to_the_site_wide_image(): void {
        $this->resetAfterTest();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $context = \core\context\coursecat::instance($categoryid);
        $this->setAdminUser();

        $this->assertNull(branding::fallback_file(), 'Nothing stored anywhere yet.');
        $this->assertNull(branding::og_source($categoryid));
        $this->assertNull(branding::ogimage($categoryid), 'No source, no image tag.');

        $fallback = self::store_jpeg(
            \core\context\system::instance()->id,
            branding::AREA_FALLBACK,
            'fallback.jpg'
        );
        $this->assertSame($fallback->get_contenthash(), branding::fallback_file()?->get_contenthash());
        $this->assertSame($fallback->get_contenthash(), branding::og_source($categoryid)?->get_contenthash());

        // The category's own address, even though the bytes are the site's.
        $url = branding::ogimage_url($categoryid, $fallback)->out(false);
        $this->assertStringEndsWith(
            "/{$context->id}/theme_dimensions/categoryogimage/{$categoryid}/"
                . $fallback->get_contenthash() . '/' . branding::OG_FILENAME,
            $url
        );

        $image = branding::ogimage($categoryid);
        $this->assertNotNull($image, 'A fallback is a source like any other.');
        $this->assertSame($url, $image['url']);
        $this->assertSame(['image/jpeg', 1200, 630], [$image['type'], $image['width'], $image['height']]);

        // The route resolves the site's bytes under the category's own id and hash, and nothing else.
        $args = [(string) $categoryid, $fallback->get_contenthash(), branding::OG_FILENAME];
        $served = branding::resolve($context, $args, branding::AREA_OGIMAGE);
        $this->assertSame($fallback->get_contenthash(), $served?->get_contenthash());
        $stale = [(string) $categoryid, sha1('stale'), branding::OG_FILENAME];
        $this->assertNull(branding::resolve($context, $stale, branding::AREA_OGIMAGE));

        // The category's own image wins over the site's the moment it exists.
        $own = self::store_jpeg($context->id, branding::AREA_HERO, 'hero.jpg');
        branding::forget_all();
        $this->assertSame($own->get_contenthash(), branding::og_source($categoryid)?->get_contenthash());
    }

    /**
     * The three badge areas store one file each, read back in the editor's order, with gaps closed.
     *
     * The area name IS the position (there is no item id to carry one, because the item id
     * carries the category), so this is the whole of the ordering rule and the whole of the
     * gap-closing rule: badge 2 left empty must not leave a hole between 1 and 3.
     */
    public function test_the_badge_areas_read_back_in_order_with_gaps_closed(): void {
        $this->resetAfterTest();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        [$first, $second, $third] = branding::AREA_BADGES;

        $this->assertSame([], branding::badges($categoryid), 'A category nobody branded has none.');
        $this->assertSame([], branding::for_category($categoryid)['badges']);

        $one = self::store_badge($categoryid, $third, 'sigma.png', base64_decode(self::PNG));
        $two = self::store_badge($categoryid, $first, 'crest-alpha.png', base64_decode(self::PNG));
        branding::forget_all();

        // Stored third-then-first, read first-then-third: the AREA decides, never the upload order.
        $this->assertSame([$first, $third], array_keys(branding::badges($categoryid)));
        $this->assertSame(
            ['crest-alpha.png', 'sigma.png'],
            array_map(static fn(\stored_file $file): string => $file->get_filename(), array_values(branding::badges($categoryid))),
            'The middle slot is empty, and the row closes the gap rather than leaving a hole.'
        );
        $this->assertSame((int) $two->get_id(), (int) branding::image($categoryid, $first)?->get_id());
        $this->assertSame((int) $one->get_id(), (int) branding::image($categoryid, $third)?->get_id());
        $this->assertNull(branding::image($categoryid, $second), 'And nothing is invented for the empty one.');
        $this->assertNull(
            branding::image($categoryid),
            'A badge is not the hero image: the areas are separate and the default is still the hero.'
        );

        // The payload carries the same three fields per badge as the image entry, in the same order.
        $payload = branding::for_category($categoryid);
        $this->assertSame([$first, $third], array_keys($payload['badges']));
        $this->assertSame(
            ['contextid', 'contenthash', 'filename'],
            array_keys($payload['badges'][$first]),
            'The same shape as the image entry: what the URL needs and nothing about a viewer.'
        );
        $this->assertSame($two->get_contenthash(), $payload['badges'][$first]['contenthash']);
        $this->assertSame('sigma.png', $payload['badges'][$third]['filename']);
    }

    /**
     * A badge URL names its own AREA, the category, the hash and the file, and its alt is the file name.
     */
    public function test_the_badge_url_names_the_area_and_the_alt_is_the_file_name(): void {
        $this->resetAfterTest();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $contextid = \core\context\coursecat::instance($categoryid)->id;
        [$first, $second] = branding::AREA_BADGES;
        $file = self::store_badge($categoryid, $second, 'crest-alpha.png', base64_decode(self::PNG));

        $payload = branding::for_category($categoryid);
        $url = branding::badge_url($categoryid, $second, $payload['badges'][$second])->out(false);
        $this->assertStringEndsWith(
            "/pluginfile.php/{$contextid}/theme_dimensions/{$second}/{$categoryid}/"
                . $file->get_contenthash() . '/crest-alpha.png',
            $url,
            'The area is the second path segment: it is what tells one badge from another.'
        );
        $this->assertStringNotContainsString(
            '/' . $first . '/',
            $url,
            'And it is the badge\'s OWN area, not the first one.'
        );

        // What both category surfaces draw: the address and the file name read as words.
        $entries = branding::badges_for_output($categoryid, $payload['badges']);
        $this->assertSame([['url' => $url, 'alt' => 'crest alpha']], $entries);
        $this->assertSame([], branding::badges_for_output($categoryid, []), 'And nothing for a category with none.');
    }

    /**
     * A save moves each badge draft area into the area of its own name, and drops the cache.
     *
     * The form names its three file managers after the areas, so this is where that convention
     * is held: a draft handed over under the name of the second badge must land in the second
     * area and nowhere else.
     */
    public function test_save_moves_each_badge_draft_into_its_own_area(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        [$first, $second] = branding::AREA_BADGES;

        branding::save($categoryid, (object) [
            $first => self::draft_with('crest-alpha.png', base64_decode(self::PNG)),
            $second => self::draft_with('dimensions.png', base64_decode(self::PNG)),
        ]);

        $badges = branding::badges($categoryid);
        $this->assertSame([$first, $second], array_keys($badges));
        $this->assertSame('crest-alpha.png', $badges[$first]->get_filename());
        $this->assertSame('dimensions.png', $badges[$second]->get_filename());
        $this->assertCount(2, branding::for_category($categoryid)['badges'], 'The cache was dropped by the save.');

        // A save that carries no badge draft at all leaves the areas exactly as they were: a
        // zero draft id is "no element was submitted", never "empty the area".
        branding::save($categoryid, (object) ['tagline' => 'Outra']);
        $this->assertCount(2, branding::badges($categoryid));

        // And a file manager whose file was removed posts a real, empty draft: that empties it.
        branding::save($categoryid, (object) [$second => file_get_unused_draft_itemid()]);
        $this->assertSame([$first], array_keys(branding::badges($categoryid)));
    }

    /**
     * An emptied file manager removes the file, drops the payload entry and the old address answers 404.
     *
     * A manager removes a cover, a film or a crest by submitting a real, empty draft, and
     * file_save_draft_area_files() over it empties the area. Three consequences are asserted
     * because the first one alone would leave a card drawing a picture that is gone: the stored
     * file, the entry of the cached payload, and the route, which must refuse the address the
     * page had minted a moment before.
     *
     * The film and the crest beside the cover are the controls: emptying one area must empty
     * exactly that one, or this would pass against a save that empties every area it is handed.
     */
    public function test_an_emptied_file_manager_removes_the_file_the_payload_entry_and_the_address(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $context = \core\context\coursecat::instance($categoryid);
        [$firstbadge] = branding::AREA_BADGES;

        branding::save($categoryid, (object) [
            branding::AREA_COVER => self::draft_with('capa.png', base64_decode(self::PNG)),
            branding::AREA_COVERVIDEO => self::draft_with('capa.mp4', self::video_bytes('cover.mp4')),
            $firstbadge => self::draft_with('crest.png', base64_decode(self::PNG)),
        ]);

        $payload = branding::for_category($categoryid);
        $this->assertNotNull($payload['cover'], 'Precondition: the cover was stored at all.');
        $this->assertNotNull($payload['covervideo'], 'Precondition: so was the film.');
        $this->assertArrayHasKey($firstbadge, $payload['badges'], 'Precondition: and so was the crest.');
        $cover = branding::image($categoryid, branding::AREA_COVER);
        $this->assertNotNull($cover);
        $address = self::args($categoryid, $cover);
        $this->assertNotNull(
            branding::resolve($context, $address, branding::AREA_COVER),
            'Precondition: the route served the cover before it was removed.'
        );

        // What the editor posts once the manager has deleted the file: a real draft, holding none.
        branding::save($categoryid, (object) [branding::AREA_COVER => file_get_unused_draft_itemid()]);

        $this->assertNull(branding::image($categoryid, branding::AREA_COVER), 'The stored cover survived the removal.');
        $after = branding::for_category($categoryid);
        $this->assertNull($after['cover'], 'The cached payload still names a cover that is gone.');
        $this->assertNull(
            branding::resolve($context, $address, branding::AREA_COVER),
            'The address the card had minted still answers with a file.'
        );

        // The controls: emptying the cover emptied the cover and nothing else.
        $this->assertNotNull($after['covervideo'], 'The film went with the cover: the save emptied more than it was asked.');
        $this->assertArrayHasKey($firstbadge, $after['badges'], 'And so did the crest.');
    }

    /**
     * A zero draft id leaves an area alone; an empty draft empties it. Both halves, over all five areas.
     *
     * The two look alike and are opposite instructions. Zero means no element was submitted (a
     * caller that never offered the field), and to core a zero draft area is "no files", so
     * passing it on would empty the target. A real draft holding nothing is the manager having
     * taken the file away.
     *
     * Each half is run over all five areas in one save, with the other half as its control: the
     * first save must change nothing at all, the second must leave all five empty.
     *
     * The route is asked for every one of the five, before and after: resolve() has a branch per
     * area, so a regression that kept serving a removed film or crest while the cover's branch
     * stayed correct would pass every other assertion here.
     */
    public function test_a_zero_draft_leaves_every_area_alone_while_an_empty_draft_empties_each(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        $seeded = [];
        foreach (array_keys(branding::single_file_areas()) as $area) {
            $bytes = $area === branding::AREA_COVERVIDEO ? self::video_bytes('cover.mp4') : base64_decode(self::PNG);
            $name = $area === branding::AREA_COVERVIDEO ? $area . '.mp4' : $area . '.png';
            $seeded[$area] = self::draft_with($name, $bytes);
        }
        branding::save($categoryid, (object) $seeded);
        // The address each area's page would have minted, taken while the file is still there.
        $context = \core\context\coursecat::instance($categoryid);
        $addresses = [];
        foreach (array_keys(branding::single_file_areas()) as $area) {
            $file = branding::image($categoryid, $area);
            $this->assertNotNull($file, 'Precondition: ' . $area . ' holds a file.');
            $addresses[$area] = self::args($categoryid, $file);
            $this->assertNotNull(
                branding::resolve($context, $addresses[$area], $area),
                'Precondition: the route served ' . $area . ' before it was removed.'
            );
        }

        // Zero everywhere, plus a value the save really does write, so the save is known to have run.
        $zeroes = array_fill_keys(array_keys(branding::single_file_areas()), 0);
        branding::save($categoryid, (object) ($zeroes + ['tagline' => 'Depois do zero']));
        $this->assertSame('Depois do zero', branding::record($categoryid)->tagline, 'Control: that save wrote nothing.');
        foreach (array_keys(branding::single_file_areas()) as $area) {
            $this->assertNotNull(branding::image($categoryid, $area), $area . ' was emptied by a zero draft id.');
        }

        // An empty draft everywhere: five removals, and the payload says so.
        $empties = [];
        foreach (array_keys(branding::single_file_areas()) as $area) {
            $empties[$area] = file_get_unused_draft_itemid();
        }
        branding::save($categoryid, (object) $empties);
        foreach (array_keys(branding::single_file_areas()) as $area) {
            $this->assertNull(branding::image($categoryid, $area), $area . ' cannot be emptied at all.');
            $this->assertNull(
                branding::resolve($context, $addresses[$area], $area),
                'The address ' . $area . ' had minted still answers with a file.'
            );
        }
        $payload = branding::for_category($categoryid);
        $this->assertNull($payload['cover']);
        $this->assertNull($payload['covervideo']);
        $this->assertSame([], $payload['badges']);
    }

    /**
     * The public page text refuses an address this site could never publish, and nothing else.
     *
     * The public text is link-only: its editor offers no upload path, but a browser can still
     * put a picture into the body with a paste or a drag-and-drop, and TinyMCE leaves it inline
     * when its upload fails - `blob:` in the live DOM, `data:` in the value that is submitted.
     * Neither can be read by anybody else: core's HTMLPurifier allows neither scheme, so the
     * public page drops the element while the row keeps the base64. A draftfile address survives
     * the purifier and breaks later instead, because the file belongs to one person's draft area
     * and core's file cron purges draft areas after four days.
     *
     * validate() is the guard for every caller that is not the form, and it throws rather than
     * storing such a row. The controls below stop this passing against a rule that refuses every
     * public text: the anchor the multimedia dialogue really inserts, an ordinary external image,
     * and the same words in prose, where they are not an address.
     */
    public function test_the_public_page_text_refuses_an_address_this_site_cannot_publish(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        foreach (
            [
                '<p><img class="img-fluid" src="data:image/png;base64,iVBORw0KGg=="></p>',
                '<p><img src="blob:http://localhost:9502/68eb9fb3-8110"></p>',
                '<p><img src="http://localhost:9502/draftfile.php/5/user/draft/602834227/a.png"></p>',
                "<p><img src='data:image/gif;base64,R0lGOD'></p>",
                '<p><video poster="data:image/png;base64,iVBORw0KGg==" src="https://example.org/a.mp4"></video></p>',
            ] as $text
        ) {
            $this->assertTrue(branding::holds_unservable_address($text), 'Admitted: ' . $text);
            try {
                branding::save($categoryid, (object) ['publicdescription' => $text]);
                $this->fail('save() stored a public text naming ' . $text);
            } catch (\invalid_parameter_exception $e) {
                $this->assertStringContainsString('cannot publish', $e->getMessage());
            }
        }

        foreach (
            [
                '<p><a class="external-media-provider" href="https://www.youtube.com/watch?v=aqz-KE-bpKQ">v</a></p>',
                '<p><a class="external-media-provider" href="https://example.org/sample-5s.mp4">s</a></p>',
                '<p><img src="https://example.org/crest.png" alt="Crest"></p>',
                '<p>Sobre data: e blob: e draftfile.php nada a declarar.</p>',
                '<p>Uma imagem normal</p>',
            ] as $text
        ) {
            $this->assertFalse(branding::holds_unservable_address($text), 'Refused: ' . $text);
            branding::save($categoryid, (object) ['publicdescription' => $text]);
            $this->assertSame($text, branding::record($categoryid)->publicdescription, 'Control refused: ' . $text);
        }
    }

    /**
     * The route serves the current file of each badge area, and refuses everything else.
     *
     * The badge branch runs is_valid_image() exactly as the hero branch does, and both halves of
     * that are asserted. An SVG crest is still served: core's
     * file_system::get_imageinfo_from_path() parses an SVG for its viewBox rather than calling
     * getimagesize(), so a well-formed SVG passes the check. And a file core refuses as an image
     * is refused from a badge area as from the hero area, which is the control in the same run;
     * without the check a same-origin .html would be served to everyone who may see the category.
     */
    public function test_the_badge_route_serves_each_area_and_refuses_the_rest(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $context = \core\context\coursecat::instance($categoryid);
        [$first, $second, $third] = branding::AREA_BADGES;
        $png = self::store_badge($categoryid, $first, 'crest-alpha.png', base64_decode(self::PNG));
        $svg = self::store_badge(
            $categoryid,
            $second,
            'dimensions.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 44"><path d="M0 0h40v44H0z"/></svg>'
        );

        $this->assertSame(
            $png->get_contenthash(),
            branding::resolve($context, self::args($categoryid, $png), $first)?->get_contenthash()
        );

        // An SVG crest, served from its own area.
        $this->assertSame(
            $svg->get_contenthash(),
            branding::resolve($context, self::args($categoryid, $svg), $second)?->get_contenthash(),
            'An SVG crest is served.'
        );
        $this->assertTrue(
            $svg->is_valid_image(),
            'Core parses an SVG for its size rather than calling getimagesize(), so this file '
                . 'passes the image check the badge branch makes.'
        );

        // A file core refuses as an image is refused from a badge area, as the hero area refuses it.
        $broken = self::store_badge($categoryid, $third, 'crest-alpha.png', 'not an image at all');
        $this->assertFalse($broken->is_valid_image(), 'Precondition: core refuses these bytes as an image.');
        $this->assertNull(
            branding::resolve($context, self::args($categoryid, $broken), $third),
            'The badge branch asks the image question.'
        );
        $herofile = self::store($categoryid, 'hero.png', 'not an image at all');
        $this->assertNull(
            branding::resolve($context, self::args($categoryid, $herofile), branding::AREA_HERO),
            'Control: the HERO area refuses the very same bytes, and the two areas now agree.'
        );

        // A stale hash, another name, the wrong number of segments, and the wrong area.
        $this->assertNull(branding::resolve($context, [(string) $categoryid, sha1('stale'), 'crest-alpha.png'], $first));
        $this->assertNull(branding::resolve($context, [(string) $categoryid, $png->get_contenthash(), 'other.png'], $first));
        $this->assertNull(branding::resolve($context, [(string) $categoryid, $png->get_contenthash()], $first));
        $this->assertNull(
            branding::resolve($context, self::args($categoryid, $png), $second),
            'The first badge is not served under the second badge\'s address.'
        );
        $this->assertNull(
            branding::resolve($context, [(string) $categoryid, $png->get_contenthash(), 'crest-alpha.png'], $third),
            'Nor under the address of a third badge that holds different bytes.'
        );
    }

    /**
     * A badge follows the hero's admission exactly: a VISITOR gets a public category's and no other.
     *
     * The public category is the control in every half, as it is for the hero: without it these
     * assertions would pass against a route that refuses every visitor.
     */
    public function test_the_badge_route_admits_a_visitor_exactly_where_the_hero_does(): void {
        global $CFG;
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $public = (int) $generator->create_category()->id;
        $listed = (int) $generator->create_category()->id;
        $unlisted = (int) $generator->create_category()->id;
        [$first] = branding::AREA_BADGES;
        $publicbadge = self::store_badge($public, $first, 'crest.png', base64_decode(self::PNG));
        $listedbadge = self::store_badge($listed, $first, 'crest.png', base64_decode(self::PNG));
        $unlistedbadge = self::store_badge($unlisted, $first, 'crest.png', base64_decode(self::PNG));
        $this->set_category_state($public, category_discoverability::STATE_PUBLIC);
        $this->set_category_state($unlisted, category_discoverability::STATE_UNLISTED);

        $CFG->forcelogin = 1;
        $this->become(null);
        $publiccontext = \core\context\coursecat::instance($public);
        $listedcontext = \core\context\coursecat::instance($listed);
        $unlistedcontext = \core\context\coursecat::instance($unlisted);

        $this->assertNotNull(
            branding::resolve($publiccontext, self::args($public, $publicbadge), $first),
            'Control: a visitor is served the crest of a public category under forcelogin.'
        );
        $this->assertNull(branding::resolve($listedcontext, self::args($listed, $listedbadge), $first));
        $this->assertNull(branding::resolve($unlistedcontext, self::args($unlisted, $unlistedbadge), $first));

        // A logged-in viewer gets the listed one and not the unlisted one, as the hero's rule says.
        $this->become($generator->create_user());
        $this->assertNotNull(branding::resolve($listedcontext, self::args($listed, $listedbadge), $first));
        $this->assertNull(branding::resolve($unlistedcontext, self::args($unlisted, $unlistedbadge), $first));

        // And the id in the path is still checked against the context it names.
        $this->setAdminUser();
        self::forget();
        $this->assertNull(branding::resolve($listedcontext, self::args($public, $publicbadge), $first));
    }

    /**
     * A cover video's type comes from its own first bytes, and its file name decides nothing.
     *
     * Core reads a stored file's mimetype off the extension, so a WebM a manager named .mp4 is
     * video/mp4 to every core reader of that record, and so is a text file called capa.mp4. The
     * assertions on get_mimetype() are preconditions: without them the refusals below could be
     * read as core already doing this work. The fixtures are real files ({@see video_bytes()}).
     */
    public function test_the_signature_names_the_video_type_and_the_file_name_names_nothing(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $area = branding::AREA_COVERVIDEO;
        // One category per file: an area holds one file, and these six are six different files.
        $ids = [];
        for ($i = 0; $i < 6; $i++) {
            $ids[] = (int) $generator->create_category()->id;
        }

        $mp4 = self::store_area($ids[0], $area, 'capa.mp4', self::video_bytes('cover.mp4'));
        $webm = self::store_area($ids[1], $area, 'capa.webm', self::video_bytes('cover.webm'));
        $this->assertSame('video/mp4', branding::video_type($mp4), 'An ftyp box at byte 4.');
        $this->assertSame('video/webm', branding::video_type($webm), 'An EBML header at byte 0.');
        $this->assertTrue(branding::is_valid_video($mp4));
        $this->assertTrue(branding::is_valid_video($webm));

        // The name says one thing and the bytes another: the bytes win, both ways round.
        $renamed = self::store_area($ids[2], $area, 'capa.mp4', self::video_bytes('cover.webm'));
        $this->assertSame(
            'video/mp4',
            $renamed->get_mimetype(),
            'Precondition: core reads the type off the extension, which is why the bytes have to be asked.'
        );
        $this->assertSame('video/webm', branding::video_type($renamed), 'And the bytes say WebM.');

        // Everything that is not one of the two, including bytes core itself calls a video.
        $text = self::store_area($ids[3], $area, 'capa.mp4', 'this is not a video at all, it is prose');
        $this->assertSame('video/mp4', $text->get_mimetype(), 'Precondition: core calls this a video.');
        $this->assertNull(branding::video_type($text));
        $this->assertFalse(branding::is_valid_video($text));

        $png = self::store_area($ids[4], $area, 'capa.mp4', base64_decode(self::PNG));
        $control = self::store_area($ids[4], branding::AREA_COVER, 'capa.png', base64_decode(self::PNG));
        $this->assertTrue(
            $control->is_valid_image(),
            'Precondition: the very same bytes under their own name are an image core accepts.'
        );
        $this->assertNull(branding::video_type($png), 'And a picture is not a video, whatever it is called.');

        $short = self::store_area($ids[5], $area, 'capa.mp4', 'ftyp');
        $this->assertNull(branding::video_type($short), 'A file shorter than the signature cannot carry one.');
    }

    /**
     * The payload names the cover and the video, and names neither when the bytes are wrong.
     *
     * The hero image is the control in both halves: it is a THIRD area holding a THIRD file, so
     * an assertion that read the cover out of the hero's entry, or that passed against a payload
     * naming nothing at all, fails here.
     */
    public function test_the_payload_names_the_cover_and_the_video_and_drops_what_is_not_one(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $categoryid = (int) $generator->create_category()->id;
        $contextid = \core\context\coursecat::instance($categoryid)->id;
        $hero = self::store($categoryid, 'hero.png', base64_decode(self::PNG));
        /* A DIFFERENT picture from the hero's: same bytes in two areas share a content hash, and
           an assertion reading the cover out of the hero's entry would pass against that. */
        $cover = self::store_jpeg($contextid, branding::AREA_COVER, 'capa.jpg');
        $video = self::store_area($categoryid, branding::AREA_COVERVIDEO, 'capa.mp4', self::video_bytes('cover.mp4'));

        $payload = branding::for_category($categoryid);
        $this->assertSame(branding::CACHED_KEYS, array_keys($payload), 'And the cached shape is still the whole list.');
        $this->assertSame(
            ['contextid', 'contenthash', 'filename'],
            array_keys($payload['cover']),
            'The cover carries what its URL needs and nothing about a viewer.'
        );
        $this->assertSame($contextid, $payload['cover']['contextid']);
        $this->assertSame($cover->get_contenthash(), $payload['cover']['contenthash']);
        $this->assertSame('capa.jpg', $payload['cover']['filename']);
        $this->assertSame(
            ['contextid', 'contenthash', 'filename', 'mimetype'],
            array_keys($payload['covervideo']),
            'The video carries one field more: the type its own bytes matched.'
        );
        $this->assertSame($video->get_contenthash(), $payload['covervideo']['contenthash']);
        $this->assertSame('video/mp4', $payload['covervideo']['mimetype']);
        $this->assertSame(
            $hero->get_contenthash(),
            $payload['image']['contenthash'],
            'Control: the hero image is a third area and keeps its own entry.'
        );
        $this->assertNotSame(
            $payload['image']['contenthash'],
            $payload['cover']['contenthash'],
            'And the card cover is not the hero image: different areas, different files.'
        );

        // A cover that is not an image and a video that is not a video: neither is ever named.
        $other = (int) $generator->create_category()->id;
        self::store($other, 'hero.png', base64_decode(self::PNG));
        self::store_area($other, branding::AREA_COVER, 'capa.png', 'not an image at all');
        self::store_area($other, branding::AREA_COVERVIDEO, 'capa.mp4', 'not a video at all');
        $dropped = branding::for_category($other);
        $this->assertNull($dropped['cover'], 'A cover GD cannot read is not linked.');
        $this->assertNull($dropped['covervideo'], 'Nor a video whose first bytes are not one.');
        $this->assertNotNull($dropped['image'], 'Control: the hero of the same category is still named.');
    }

    /**
     * The cover route serves the still, on exactly the hero's checks, and refuses everything else.
     */
    public function test_the_cover_route_serves_the_still_and_refuses_anything_else(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $context = \core\context\coursecat::instance($categoryid);
        $area = branding::AREA_COVER;
        $cover = self::store_area($categoryid, $area, 'capa.png', base64_decode(self::PNG));

        $this->assertSame(
            $cover->get_contenthash(),
            branding::resolve($context, self::args($categoryid, $cover), $area)?->get_contenthash(),
            'Control: the current content under its own name.'
        );
        $this->assertNull(branding::resolve($context, [(string) $categoryid, sha1('stale'), 'capa.png'], $area));
        $this->assertNull(branding::resolve($context, [(string) $categoryid, $cover->get_contenthash(), 'x.png'], $area));
        $this->assertNull(branding::resolve($context, [(string) $categoryid, $cover->get_contenthash()], $area));
        $this->assertNull(
            branding::resolve($context, self::args($categoryid, $cover), branding::AREA_HERO),
            'The cover is not served from the hero\'s address.'
        );

        // A file that is not an image, refused exactly as the hero area refuses the same bytes.
        $broken = (int) self::getDataGenerator()->create_category()->id;
        $brokencontext = \core\context\coursecat::instance($broken);
        $file = self::store_area($broken, $area, 'capa.png', 'not an image at all');
        $this->assertFalse($file->is_valid_image(), 'Precondition: core refuses these bytes as an image.');
        $this->assertNull(branding::resolve($brokencontext, self::args($broken, $file), $area));
        $herofile = self::store($broken, 'hero.png', 'not an image at all');
        $this->assertNull(
            branding::resolve($brokencontext, self::args($broken, $herofile), branding::AREA_HERO),
            'Control: the hero area refuses the very same bytes, and the two areas agree.'
        );
    }

    /**
     * The video route serves an MP4 and a WebM and refuses a file that is not one.
     *
     * The refusal is asserted TWICE over: the route answers null and the cached payload never
     * named the file in the first place, which is the pair that makes "a video is never trusted
     * by its name" true of a page as well as of a request. A card that linked a file the route
     * refuses would be a control that looks broken rather than a still that simply never plays.
     */
    public function test_the_video_route_serves_an_mp4_and_a_webm_and_refuses_what_is_not_one(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $area = branding::AREA_COVERVIDEO;

        $mp4id = (int) $generator->create_category()->id;
        $mp4context = \core\context\coursecat::instance($mp4id);
        $mp4 = self::store_area($mp4id, $area, 'capa.mp4', self::video_bytes('cover.mp4'));
        $webmid = (int) $generator->create_category()->id;
        $webmcontext = \core\context\coursecat::instance($webmid);
        $webm = self::store_area($webmid, $area, 'capa.webm', self::video_bytes('cover.webm'));

        $this->assertSame(
            $mp4->get_contenthash(),
            branding::resolve($mp4context, self::args($mp4id, $mp4), $area)?->get_contenthash()
        );
        $this->assertSame(
            $webm->get_contenthash(),
            branding::resolve($webmcontext, self::args($webmid, $webm), $area)?->get_contenthash()
        );
        $this->assertSame('video/mp4', branding::for_category($mp4id)['covervideo']['mimetype']);
        $this->assertSame('video/webm', branding::for_category($webmid)['covervideo']['mimetype']);

        // A text file under a video's name: refused by the route AND absent from the payload.
        $fakeid = (int) $generator->create_category()->id;
        $fakecontext = \core\context\coursecat::instance($fakeid);
        $fake = self::store_area($fakeid, $area, 'capa.mp4', 'not a video at all, it is prose');
        $this->assertNull(
            branding::resolve($fakecontext, self::args($fakeid, $fake), $area),
            'The route reads the bytes rather than the name.'
        );
        $this->assertNull(branding::for_category($fakeid)['covervideo'], 'And no page ever names it.');

        // The hashed address is still the whole address: a stale hash and another name are 404.
        $this->assertNull(branding::resolve($mp4context, [(string) $mp4id, sha1('stale'), 'capa.mp4'], $area));
        $this->assertNull(branding::resolve($mp4context, [(string) $mp4id, $mp4->get_contenthash(), 'x.mp4'], $area));
        $this->assertNull(branding::resolve($mp4context, [(string) $mp4id, $mp4->get_contenthash()], $area));
        $this->assertNull(
            branding::resolve($mp4context, self::args($webmid, $webm), $area),
            'And one category\'s context never pairs with another category\'s path.'
        );
    }

    /**
     * The cover and the video admit a VISITOR exactly where the hero does, and nowhere else.
     *
     * The public category is the control in every half, as it is for the hero and the crests:
     * without it these assertions would pass against a route that refuses every visitor.
     */
    public function test_the_cover_areas_admit_a_visitor_exactly_where_the_hero_does(): void {
        global $CFG;
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $public = (int) $generator->create_category()->id;
        $listed = (int) $generator->create_category()->id;
        $unlisted = (int) $generator->create_category()->id;
        $covers = [];
        $videos = [];
        foreach ([$public, $listed, $unlisted] as $id) {
            $covers[$id] = self::store_area($id, branding::AREA_COVER, 'capa.png', base64_decode(self::PNG));
            $videos[$id] = self::store_area($id, branding::AREA_COVERVIDEO, 'capa.mp4', self::video_bytes('cover.mp4'));
        }
        $this->set_category_state($public, category_discoverability::STATE_PUBLIC);
        $this->set_category_state($unlisted, category_discoverability::STATE_UNLISTED);

        $CFG->forcelogin = 1;
        $this->become(null);
        $contexts = [];
        foreach ([$public, $listed, $unlisted] as $id) {
            $contexts[$id] = \core\context\coursecat::instance($id);
        }

        $this->assertNotNull(
            branding::resolve($contexts[$public], self::args($public, $covers[$public]), branding::AREA_COVER),
            'Control: a visitor is served the cover of a public category under forcelogin.'
        );
        $this->assertNotNull(
            branding::resolve($contexts[$public], self::args($public, $videos[$public]), branding::AREA_COVERVIDEO),
            'Control: and its video.'
        );
        foreach ([$listed, $unlisted] as $id) {
            $this->assertNull(branding::resolve($contexts[$id], self::args($id, $covers[$id]), branding::AREA_COVER));
            $this->assertNull(
                branding::resolve($contexts[$id], self::args($id, $videos[$id]), branding::AREA_COVERVIDEO)
            );
        }

        // A logged-in viewer gets the listed one and not the unlisted one, as the hero's rule says.
        $this->become($generator->create_user());
        $this->assertNotNull(
            branding::resolve($contexts[$listed], self::args($listed, $covers[$listed]), branding::AREA_COVER)
        );
        $this->assertNotNull(
            branding::resolve($contexts[$listed], self::args($listed, $videos[$listed]), branding::AREA_COVERVIDEO)
        );
        $this->assertNull(
            branding::resolve($contexts[$unlisted], self::args($unlisted, $covers[$unlisted]), branding::AREA_COVER)
        );
        $this->assertNull(
            branding::resolve($contexts[$unlisted], self::args($unlisted, $videos[$unlisted]), branding::AREA_COVERVIDEO)
        );
    }

    /**
     * A save moves the cover and the video drafts into the areas of their own names.
     *
     * The same convention the three crests are held to, for the same reason: the editor names its
     * file managers after the file areas, so an element renamed in the form would store a
     * manager's file nowhere at all with the page still saving.
     */
    public function test_save_moves_the_cover_and_the_video_drafts_into_their_own_areas(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        branding::save($categoryid, (object) [
            branding::AREA_COVER => self::draft_with('capa.png', base64_decode(self::PNG)),
            branding::AREA_COVERVIDEO => self::draft_with('capa.mp4', self::video_bytes('cover.mp4')),
        ]);

        $cover = branding::image($categoryid, branding::AREA_COVER);
        $video = branding::image($categoryid, branding::AREA_COVERVIDEO);
        $this->assertSame('capa.png', $cover?->get_filename());
        $this->assertSame('capa.mp4', $video?->get_filename());
        $this->assertNull(branding::image($categoryid), 'And neither landed in the hero area.');
        $payload = branding::for_category($categoryid);
        $this->assertNotNull($payload['cover'], 'The cache was dropped by the save.');
        $this->assertSame('video/mp4', $payload['covervideo']['mimetype']);

        // A save carrying no cover draft at all leaves both areas exactly as they were.
        branding::save($categoryid, (object) ['tagline' => 'Outra']);
        $this->assertNotNull(branding::image($categoryid, branding::AREA_COVER));
        $this->assertNotNull(branding::image($categoryid, branding::AREA_COVERVIDEO));

        // And a file manager whose file was removed posts a real, empty draft: that empties it.
        branding::save($categoryid, (object) [branding::AREA_COVERVIDEO => file_get_unused_draft_itemid()]);
        $this->assertNull(branding::image($categoryid, branding::AREA_COVERVIDEO));
        $this->assertNotNull(branding::image($categoryid, branding::AREA_COVER), 'Control: the cover is untouched.');
    }

    /**
     * The editor's initial data carries a draft area per cover area, each holding what is stored.
     *
     * The other half of the "named after its area" convention, and the half nothing on screen
     * would report: a file manager whose draft area was never prepared opens empty over a
     * category that has a cover, so the page looks as though the upload never worked.
     */
    public function test_the_editors_data_prepares_a_draft_area_for_the_cover_and_the_video(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        self::store_area($categoryid, branding::AREA_COVER, 'capa.png', base64_decode(self::PNG));
        self::store_area($categoryid, branding::AREA_COVERVIDEO, 'capa.mp4', self::video_bytes('cover.mp4'));

        $data = branding::form_data($categoryid);
        $usercontextid = \core\context\user::instance($GLOBALS['USER']->id)->id;
        foreach (
            [branding::AREA_COVER => 'capa.png', branding::AREA_COVERVIDEO => 'capa.mp4'] as $area => $filename
        ) {
            $draftitemid = (int) ($data->$area ?? 0);
            $this->assertGreaterThan(0, $draftitemid, $area . ' has no draft area on the form at all.');
            $files = get_file_storage()->get_area_files(
                $usercontextid,
                'user',
                'draft',
                $draftitemid,
                'filename',
                false
            );
            $this->assertSame(
                [$filename],
                array_values(array_map(static fn(\stored_file $file): string => $file->get_filename(), $files)),
                'The picker of ' . $area . ' would open empty over a category that has a file.'
            );
        }
    }

    /**
     * Each cover URL names its OWN area, the category, the hash and the file name.
     */
    public function test_the_cover_urls_name_their_own_areas(): void {
        $this->resetAfterTest();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $contextid = \core\context\coursecat::instance($categoryid)->id;
        $cover = self::store_area($categoryid, branding::AREA_COVER, 'capa.png', base64_decode(self::PNG));
        $video = self::store_area($categoryid, branding::AREA_COVERVIDEO, 'capa.mp4', self::video_bytes('cover.mp4'));
        $payload = branding::for_category($categoryid);

        $coverurl = branding::cover_url($categoryid, $payload['cover'])->out(false);
        $this->assertStringEndsWith(
            "/pluginfile.php/{$contextid}/theme_dimensions/categorycover/{$categoryid}/"
                . $cover->get_contenthash() . '/capa.png',
            $coverurl
        );
        $videourl = branding::covervideo_url($categoryid, $payload['covervideo'])->out(false);
        $this->assertStringEndsWith(
            "/pluginfile.php/{$contextid}/theme_dimensions/categorycovervideo/{$categoryid}/"
                . $video->get_contenthash() . '/capa.mp4',
            $videourl
        );
        $this->assertStringNotContainsString('/categoryhero/', $coverurl, 'Not the hero\'s area.');
        $this->assertStringNotContainsString('/categorycover/', $videourl, 'And the video is not the still.');
    }

    /**
     * The video's size limit is the site setting's, and an unwritten setting is twenty megabytes.
     *
     * A default read with a falsy fallback would report a stored value of nothing as a usable
     * limit and a stored zero as one too; the select offers neither, and a picker bounded by zero
     * is a field that refuses every file with no explanation. The settings page is read from its
     * own source, because a setting nothing registers has a default nobody can change.
     */
    public function test_the_video_size_limit_comes_from_the_setting_and_falls_back_to_twenty_megabytes(): void {
        global $CFG;
        $this->resetAfterTest();

        $this->assertSame(20971520, branding::DEFAULT_COVERVIDEO_MAXBYTES, 'The default limit is 20 MB.');
        $this->assertSame(branding::DEFAULT_COVERVIDEO_MAXBYTES, branding::covervideo_maxbytes());
        $this->assertSame(branding::DEFAULT_COVERVIDEO_MAXBYTES, branding::covervideo_options()['maxbytes']);

        set_config(branding::SETTING_COVERVIDEO_MAXBYTES, 52428800, branding::COMPONENT);
        $this->assertSame(52428800, branding::covervideo_maxbytes(), 'A site that chose another limit gets it.');
        $this->assertSame(52428800, branding::covervideo_options()['maxbytes']);

        set_config(branding::SETTING_COVERVIDEO_MAXBYTES, '0', branding::COMPONENT);
        $this->assertSame(
            branding::DEFAULT_COVERVIDEO_MAXBYTES,
            branding::covervideo_maxbytes(),
            'A stored zero is not a choice the select offers, so it reads as nobody having chosen.'
        );

        // The two option sets the editor's pickers take, spelled once each.
        $this->assertSame(['web_image'], branding::cover_options()['accepted_types']);
        $this->assertSame(1, branding::cover_options()['maxfiles']);
        $this->assertSame(['.mp4', '.webm'], branding::covervideo_options()['accepted_types']);
        $this->assertSame(1, branding::covervideo_options()['maxfiles']);

        $source = file_get_contents($CFG->dirroot . '/theme/dimensions/settings.php');
        $this->assertStringContainsString(
            'branding::SETTING_COVERVIDEO_MAXBYTES',
            $source,
            'The setting is registered, on the Vitrine tab, or its default is one nobody can change.'
        );
        $this->assertStringContainsString('branding::DEFAULT_COVERVIDEO_MAXBYTES', $source);
        $this->assertStringContainsString('get_max_upload_sizes(', $source, 'And its vocabulary is core\'s own sizes.');
    }

    /**
     * On a site whose upload limit is under 20 MB, core's sizes drop the default, so the setting keeps it among its choices
     * in their order, and the installer's write of it is stored: the setting is no longer "new" on every upgrade. The
     * control is a site whose limit already holds the default, which gets core's list unchanged.
     */
    public function test_the_video_size_default_stays_a_choice_under_a_small_upload_limit(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $this->resetAfterTest();
        // The admin tree holds the theme's page only for somebody who may configure it.
        $this->setAdminUser();

        $small = get_max_upload_sizes(1048576, 0, 0, branding::covervideo_maxbytes());
        $this->assertArrayNotHasKey('20971520', $small, 'Precondition: core drops the default under a 1 MB limit.');
        $choices = branding::covervideo_choices($small);
        $this->assertArrayHasKey('20971520', $choices);
        $this->assertSame('0', (string) array_key_first($choices), 'Core\'s "site upload limit" line stays first.');
        $sizes = array_map('intval', array_slice(array_keys($choices), 1));
        $sorted = $sizes;
        rsort($sorted);
        $this->assertSame($sorted, $sizes, 'The rest keeps core\'s descending order.');

        $large = get_max_upload_sizes(104857600, 0, 0, branding::covervideo_maxbytes());
        $this->assertArrayHasKey('20971520', $large, 'Precondition: a 100 MB limit holds the default.');
        $this->assertSame($large, branding::covervideo_choices($large), 'Control: core\'s list, unchanged.');

        // The installer's path, under the small limit: core applies the page's defaults, and this one is stored.
        $CFG->maxbytes = 1048576;
        unset_config(branding::SETTING_COVERVIDEO_MAXBYTES, branding::COMPONENT);
        $page = admin_get_root(true, true)->locate('theme_dimensions');
        $this->assertNotNull($page, 'The theme settings page is in the admin tree.');
        $this->assertStringContainsString(
            branding::SETTING_COVERVIDEO_MAXBYTES,
            admin_output_new_settings_by_page($page)['theme_dimensions'] ?? '',
            'Precondition: unset, the setting counts as new.'
        );
        admin_apply_default_settings($page, false);
        $this->assertSame('20971520', (string) get_config(branding::COMPONENT, branding::SETTING_COVERVIDEO_MAXBYTES));
        $this->assertStringNotContainsString(
            branding::SETTING_COVERVIDEO_MAXBYTES,
            admin_output_new_settings_by_page(admin_get_root(true, true)->locate('theme_dimensions'))['theme_dimensions'] ?? '',
            'Stored, it is no longer new.'
        );
    }

    /**
     * Every file field of the editor takes a copy, and an alias never reaches its area.
     *
     * A file manager's default admits an alias and a controlled link beside a copy. An alias is a
     * file whose bytes live elsewhere: delete the source and the route holds a stored_file it
     * cannot read.
     *
     * Two halves. The option sets name FILE_INTERNAL and nothing else - all four helpers, so all
     * six fields. And the behaviour, through core's own save: a draft holding an alias leaves
     * the area empty, on both of save()'s paths (the hero's own draft argument, and the
     * single-file areas' loop), while a copy of the same image in the same place is saved - the
     * control, without which "nothing was saved" would also be what a broken save looks like.
     */
    public function test_every_file_field_takes_a_copy_and_an_alias_never_reaches_its_area(): void {
        global $CFG;
        require_once($CFG->dirroot . '/repository/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $sets = [
            'hero' => branding::filemanager_options(),
            'cover' => branding::cover_options(),
            'film' => branding::covervideo_options(),
            'crest' => branding::badge_options(),
        ];
        foreach ($sets as $name => $options) {
            $this->assertSame(FILE_INTERNAL, $options['return_types'], 'The ' . $name . ' takes a copy and nothing else.');
        }
        foreach (branding::single_file_areas() as $area => $options) {
            $this->assertSame(FILE_INTERNAL, $options['return_types'], $area . ' is built with a copy-only option set.');
        }

        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        // The control: a copy is saved, through both paths.
        branding::save(
            $categoryid,
            (object) [branding::AREA_COVER => self::draft_with('capa.png', base64_decode(self::PNG))],
            self::draft_with('fundo.png', base64_decode(self::PNG))
        );
        $this->assertNotNull(branding::image($categoryid), 'Control: a copy reaches the hero area.');
        $this->assertNotNull(branding::image($categoryid, branding::AREA_COVER), 'Control: a copy reaches the cover area.');

        // An alias in each draft: core's save skips it, so each area ends up empty.
        branding::save(
            $categoryid,
            (object) [branding::AREA_COVER => self::draft_with_alias('capa-alias.png')],
            self::draft_with_alias('fundo-alias.png')
        );
        $this->assertNull(branding::image($categoryid), 'An alias reached the hero area.');
        $this->assertNull(branding::image($categoryid, branding::AREA_COVER), 'An alias reached the cover area.');
        $payload = branding::for_category($categoryid);
        $this->assertNull($payload['image'], 'And the cached payload names no hero image.');
        $this->assertNull($payload['cover'], 'Nor a cover.');
    }

    /**
     * The settings page builds on a site that has no maxbytes yet, without a PHP warning.
     *
     * During a fresh install the admin tree is built before core's defaults are stored, so
     * $CFG->maxbytes does not exist yet, and moodle-plugin-ci's install step treats the resulting
     * warning as fatal. A site that is already installed never shows it. This builds the whole
     * admin tree, this theme's page included, on a site whose property is gone, and turns any
     * warning raised from this theme's files into a failure by name, because a PHPUnit warning
     * is not a failure.
     *
     * Changes that must make it fail: reading $CFG->maxbytes bare in settings.php.
     */
    public function test_the_settings_page_builds_on_a_site_with_no_maxbytes_yet(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        unset($CFG->maxbytes);

        $raised = [];
        set_error_handler(static function (int $severity, string $message, string $file) use (&$raised): bool {
            if (str_contains($file, 'dimensions')) {
                $raised[] = basename($file) . ': ' . $message;
            }
            return true;
        });
        try {
            $root = admin_get_root(true, true);
        } finally {
            restore_error_handler();
        }

        $this->assertNotNull(
            $root->locate('theme_dimensions'),
            'Precondition: the tree that was built really holds this theme\'s page.'
        );
        $this->assertSame([], $raised, 'A fresh install has no maxbytes; the settings page must not read it bare.');
    }

    /**
     * Every cover area reaches this route from the theme's pluginfile callback.
     */
    public function test_the_cover_areas_are_wired_into_the_pluginfile_callback(): void {
        global $CFG;
        $source = file_get_contents($CFG->dirroot . '/theme/dimensions/lib.php');
        $this->assertStringContainsString('$branding::AREA_COVER', $source);
        $this->assertStringContainsString('$branding::AREA_COVERVIDEO', $source);
    }

    /**
     * The values hex() accepts, the spelling it returns, and everything it refuses.
     *
     * The whole feature's defence rests on this one function: it is what lets a free colour be
     * written into a style attribute at all, so its refusals are the interesting half.
     *
     * @return array Cases of value and the expected result, null for a refusal.
     */
    public static function hex_provider(): array {
        return [
            'six digits' => ['#12484f', '#12484f'],
            'upper case' => ['#12484F', '#12484f'],
            'mixed case' => ['#1A2b3C', '#1a2b3c'],
            'three digits' => ['#fff', '#ffffff'],
            'three digits expanded pairwise' => ['#1a2', '#11aa22'],
            'surrounding space' => ['  #12484f  ', '#12484f'],
            /* The hash is required, here and in the editor's module, which parses with the same
               vocabulary: a spelling accepted here and refused there would be a panel grading a
               value the form stores, or a note saying "invalid" over a field that saves. The
               spellings table of the shared fixture holds the two together. */
            'no hash' => ['12484f', null],
            'no hash, three digits' => ['fff', null],
            'empty' => ['', null],
            'a colour name' => ['crimson', null],
            'five digits' => ['#12345', null],
            'seven digits' => ['#1234567', null],
            'a declaration after it' => ['#fff;background:url(x)', null],
            'a function' => ['rgb(1,2,3)', null],
            'a var()' => ['var(--bs-primary)', null],
            'an escape attempt' => ['#fff" onload="x', null],
            'non-hex letters' => ['#gggggg', null],
            'an inner space' => ['#12 484f', null],
        ];
    }

    /**
     * hex() normalises what it accepts to one spelling and refuses everything else.
     *
     * @param string $value The value handed in.
     * @param string|null $expected The normalised colour, or null for a refusal.
     */
    #[DataProvider('hex_provider')]
    public function test_hex_normalises_or_refuses(string $value, ?string $expected): void {
        $this->assertSame($expected, branding::hex($value));
    }

    /**
     * The contrast maths agree with the fixture the editor's module is measured against.
     *
     * tests/fixtures/contrast_pairs.json is read here and by categoryhero_source_test against the
     * module's own constants: the panel that praises a pair and the server that refuses it have to
     * compute the same ratio.
     */
    public function test_the_contrast_maths_match_the_shared_fixture(): void {
        $fixture = json_decode(file_get_contents(__DIR__ . '/../../fixtures/contrast_pairs.json'), true);
        $this->assertIsArray($fixture, 'The shared fixture must be readable JSON.');
        $this->assertCount(5, $fixture['pairs'], 'Five pairs, as both readers expect.');
        $tolerance = (float) $fixture['tolerance'];
        foreach ($fixture['pairs'] as $pair) {
            $this->assertEqualsWithDelta(
                (float) $pair['ratio'],
                branding::contrast($pair['bg'], $pair['ink']),
                $tolerance,
                $pair['bg'] . ' on ' . $pair['ink']
            );
        }
        // The order of the two arguments cannot matter: the ratio is symmetric by construction.
        $this->assertSame(branding::contrast('#070093', '#ffffff'), branding::contrast('#ffffff', '#070093'));
        // A value that is not a colour reads as the worst possible ratio, so every floor fails closed.
        $this->assertSame(1.0, branding::contrast('crimson', '#ffffff'));
        $this->assertSame(1.0, branding::contrast('#ffffff', 'var(--x)'));
    }

    /**
     * The stops are derived from the band colour: darker head, the colour itself, lighter tail.
     */
    public function test_the_stops_are_derived_from_the_band_colour(): void {
        // 7,0,147 darkened 18% is 6,0,121; lightened 12% towards white is 37,31,160.
        $this->assertSame(
            ['from' => '#060079', 'mid' => '#070093', 'to' => '#251fa0'],
            branding::stops('#070093')
        );
        $this->assertSame(
            ['from' => '#0f3b41', 'mid' => '#12484f', 'to' => '#2e5e64'],
            branding::stops('#12484f')
        );
        // The MID stop is always the colour itself, whatever it is: the tab strip paints it.
        foreach (['#000000', '#ffffff', '#ffc107'] as $bg) {
            $this->assertSame($bg, branding::stops($bg)['mid']);
        }
        // Black cannot darken and white cannot lighten, and neither may leave the vocabulary.
        $this->assertSame('#000000', branding::stops('#000000')['from']);
        $this->assertSame('#ffffff', branding::stops('#ffffff')['to']);
        // A value that is not a colour yields the default's stops rather than a broken string.
        $this->assertSame(branding::stops(branding::DEFAULT_BG), branding::stops('#fff;background:url(x)'));
    }

    /**
     * mix() lays one colour over another in whole ten-thousandths, rounding half up, and fails closed.
     *
     * The green channel of the first case is the half case (0 and 255 at .5 give 127.5), so it
     * pins the rounding rule, and the chevron's composite is passed as the very expression
     * {@see population::mark_alpha()} evaluates rather than as a literal.
     */
    public function test_mix_lays_the_top_colour_over_the_base_and_fails_closed(): void {
        $this->assertSame('#8380c9', branding::mix('#070093', '#ffffff', 0.5), '127.5 rounds up to 128 (0x80).');
        $this->assertSame('#3933a9', branding::mix('#070093', '#ffffff', 0.2));
        $this->assertSame('#5c58b8', branding::mix('#070093', '#ffffff', 1 - (1 - 0.2) * (1 - 0.18)));
        // The two ends are the two colours, lower-cased.
        $this->assertSame('#070093', branding::mix('#070093', '#FFFFFF', 0));
        $this->assertSame('#ffffff', branding::mix('#070093', '#FFFFFF', 1));
        // Clamped rather than extrapolated.
        $this->assertSame(branding::mix('#070093', '#ffffff', 0), branding::mix('#070093', '#ffffff', -1));
        $this->assertSame(branding::mix('#070093', '#ffffff', 1), branding::mix('#070093', '#ffffff', 2));
        // A value that is not a colour, on either side, is no composite at all.
        $this->assertNull(branding::mix('crimson', '#ffffff', 0.5));
        $this->assertNull(branding::mix('#070093', 'var(--x)', 0.5));
    }

    /**
     * rgba() prints the channels of a sanitised colour and clamps the opacity.
     */
    public function test_rgba_prints_sanitised_channels(): void {
        $this->assertSame('rgba(7, 0, 147, 0.78)', branding::rgba('#070093', 0.78));
        $this->assertSame('rgba(255, 255, 255, 0.86)', branding::rgba('#FFF', 0.86));
        $this->assertSame('rgba(7, 0, 147, 1)', branding::rgba('#070093', 2.0), 'Clamped, never printed as 2.');
        $this->assertSame('rgba(7, 0, 147, 0)', branding::rgba('#070093', -1.0));
        /* The transparent overlay, which is a real setting and not only a clamp: the alpha prints
           as "0" and never as "0." - number_format gives "0.00" and the two rtrims have to take
           the trailing zeros and the point, or the declaration is invalid CSS and the browser
           drops the whole inline style. */
        $this->assertSame('rgba(7, 0, 147, 0)', branding::rgba('#070093', 0.0));
        $this->assertStringEndsWith(' 0)', branding::rgba('#070093', 0.0));
        $this->assertSame(
            branding::rgba(branding::DEFAULT_BG, 0.5),
            branding::rgba('#fff" onload="x', 0.5),
            'A value that is not a colour yields the default, never itself.'
        );
    }

    /**
     * The floor is a refusal, and it is measured rather than guessed.
     *
     * The two pairs are one either side of 4.5:1 - 3.90:1 and 4.61:1 - and the test asserts the
     * ratio it relies on before asserting the refusal, so a change to the maths cannot quietly
     * turn this into a test of two arbitrary greys.
     */
    public function test_the_contrast_floor_refuses_one_pair_and_accepts_the_next(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        $under = branding::contrast('#818181', '#ffffff');
        $over = branding::contrast('#757575', '#ffffff');
        $this->assertEqualsWithDelta(3.8957, $under, 0.001, 'The precondition of the refusal.');
        $this->assertEqualsWithDelta(4.6075, $over, 0.001, 'The precondition of the acceptance.');
        $this->assertLessThan(branding::FLOOR, $under);
        $this->assertGreaterThan(branding::FLOOR, $over);

        try {
            branding::save($categoryid, (object) ['bgcolour' => '#818181', 'inkcolour' => '#ffffff']);
            $this->fail('A pair under the floor was stored.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('3.90', $e->getMessage(), 'The measured ratio is in the message.');
            $this->assertSame(0, $DB->count_records(branding::TABLE, ['categoryid' => $categoryid]));
        }

        $row = branding::save($categoryid, (object) ['bgcolour' => '#757575', 'inkcolour' => '#ffffff']);
        $this->assertSame('#757575', $row->bgcolour);
        $this->assertSame(1, $DB->count_records(branding::TABLE, ['categoryid' => $categoryid]));
    }

    /**
     * format_ratio() prints two decimals under ten and one from ten up, as the panel does.
     */
    public function test_format_ratio_prints_what_the_panel_prints(): void {
        $this->assertSame('4.61', branding::format_ratio(4.6075));
        $this->assertSame('3.90', branding::format_ratio(3.8957));
        $this->assertSame('1.00', branding::format_ratio(1.0));
        $this->assertSame('10.2', branding::format_ratio(10.166694));
        $this->assertSame('21.0', branding::format_ratio(21.0));
        $this->assertSame('10.0', branding::format_ratio(9.995), 'The boundary rounds up into the one-decimal form.');
    }

    /**
     * The server and the editor's module accept and refuse exactly the same spellings.
     *
     * The five pairs of the fixture measure the maths; this measures the VOCABULARY, which the
     * pairs cannot, because every one of them is already written the one way both sides accept.
     * The same table is read by categoryhero_source_test against the module's own HEXRE, so a
     * leading hash made optional on one side alone goes red here or there - the manager would
     * otherwise meet a panel calling a value invalid over a field that saves it, or the reverse.
     */
    public function test_the_two_implementations_accept_the_same_spellings(): void {
        $fixture = json_decode(file_get_contents(__DIR__ . '/../../fixtures/contrast_pairs.json'), true);
        $this->assertIsArray($fixture['spellings'] ?? null, 'The shared fixture carries the spellings table.');
        foreach ($fixture['spellings'] as $case) {
            $result = branding::hex((string) $case['value']);
            if ($case['accepted']) {
                $this->assertNotNull($result, 'Accepted on both sides: ' . $case['value'] . ' - ' . $case['why']);
                $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $result, 'Normalised to the one spelling.');
            } else {
                $this->assertNull($result, 'Refused on both sides: ' . $case['value'] . ' - ' . $case['why']);
            }
        }
    }

    /**
     * @var array The four audited reference colours: key => [bg, ink].
     *
     * The editor no longer offers them and branding no longer names them. They stay here for two
     * reasons: they are the fixed set the overlay measurements below are taken against, which
     * must not move with the editor, and the default pair is measured beside them. Nothing in
     * production reads this table.
     */
    private const REFERENCE_COLOURS = [
        'navy' => ['bg' => '#070093', 'ink' => '#ffffff'],
        'slate' => ['bg' => '#12484f', 'ink' => '#ffffff'],
        'plum' => ['bg' => '#3d2a5e', 'ink' => '#ffffff'],
        'graphite' => ['bg' => '#2f3338', 'ink' => '#ffffff'],
    ];

    /**
     * The reference colours and the default pair are in the one spelling and all over the floor.
     *
     * Nothing else holds this table to the colours the measurements below are about. The default
     * pair is a dark blue with white ink, no institution's colour: a site that never branded a
     * category gets a band that clears AAA on its own.
     */
    public function test_the_reference_colours_and_the_default_pair_are_audited(): void {
        foreach (self::REFERENCE_COLOURS as $key => $pair) {
            $this->assertSame($pair['bg'], branding::hex($pair['bg']), 'Stored in the one spelling: ' . $key);
            $this->assertSame($pair['ink'], branding::hex($pair['ink']));
            $this->assertGreaterThanOrEqual(
                branding::FLOOR,
                branding::contrast($pair['bg'], $pair['ink']),
                'An audited colour reads under the floor: ' . $key
            );
        }
        $this->assertSame('#0a4a85', branding::DEFAULT_BG, 'The default band is the dark hero blue.');
        $this->assertSame('#ffffff', branding::DEFAULT_INK);
        $this->assertSame(branding::DEFAULT_BG, branding::hex(branding::DEFAULT_BG), 'Stored in the one spelling.');
        $this->assertEqualsWithDelta(9.01, branding::contrast(branding::DEFAULT_BG, branding::DEFAULT_INK), 0.01);
        $this->assertGreaterThanOrEqual(
            branding::FLOOR,
            branding::contrast(branding::DEFAULT_BG, branding::DEFAULT_INK),
            'The default pair reads under the floor.'
        );
    }

    /**
     * The measurement of what the AA floor does not cover: the colour over a photograph.
     *
     * validate() certifies the opaque pair, which is what the solid band paints and what the
     * editor's panel grades. On the image layout the same colour is painted translucent at the
     * overlay the manager chose, over a picture the theme knows nothing about, so the text can
     * read lower than the certified pair - the worst case being a bright photograph under a light
     * band. That is accepted deliberately (scss/category.scss records 4.1:1 on the lightest stop).
     * The 0 overlay paints no colour at all and leaves the text on the bare photograph, where
     * nothing the panel measures applies; the help text carries that warning.
     *
     * The numbers are measured here so that an overlay removed, a colour re-toned or a stop
     * formula changed cannot move them quietly.
     */
    public function test_the_overlay_does_not_carry_the_floor_over_a_photograph(): void {
        $white = '#ffffff';
        // The opaque pair - what validate() certifies and the panel grades - is over the floor.
        foreach (self::REFERENCE_COLOURS as $pair) {
            $this->assertGreaterThan(branding::FLOOR, branding::contrast($pair['bg'], $pair['ink']));
        }
        /* The same colour over a WHITE photograph, at each of the four overlays. Measured,
           not asserted as "at least": a number that drifts is the finding. At 0 the colour is
           not painted at all, so every one of them is the contrast of the ink against the bare
           white picture - 1.0 for white ink, which is the whole warning in one number. */
        $measured = [
            'navy' => [0 => 1.00, 60 => 4.93, 78 => 8.90, 90 => 12.33],
            'slate' => [0 => 1.00, 60 => 3.39, 78 => 5.47, 90 => 7.68],
            'plum' => [0 => 1.00, 60 => 3.73, 78 => 6.30, 90 => 9.22],
            'graphite' => [0 => 1.00, 60 => 3.71, 78 => 6.32, 90 => 9.33],
        ];
        $this->assertSame(
            branding::OVERLAYS,
            array_keys($measured['navy']),
            'An overlay the editor offers is not measured here, or one measured here is not offered.'
        );
        $under = 0;
        foreach ($measured as $key => $byoverlay) {
            $pair = self::REFERENCE_COLOURS[$key];
            foreach ($byoverlay as $overlay => $expected) {
                $painted = self::composite($pair['bg'], $overlay, $white);
                $ratio = branding::contrast($painted, $pair['ink']);
                $this->assertEqualsWithDelta($expected, $ratio, 0.01, $key . ' at ' . $overlay . '% over white');
                if ($ratio < branding::FLOOR) {
                    $under++;
                }
            }
        }
        $this->assertSame(
            7,
            $under,
            'Seven colour-and-overlay combinations read under the floor over a white photograph - '
                . 'the four transparent ones, where no colour is painted at all, and three at the '
                . 'lightest overlay; none at the default of 78%.'
        );
        /* The default pair clears the floor over a white picture at 78% and 90%; at 60% no colour can
           (3.27:1 here), which stays a manager's explicit choice that D31 measures and does not
           refuse. The default layout is the solid band, where the pair reads 9.01:1. */
        $defaults = [0 => 1.00, 60 => 3.27, 78 => 5.08, 90 => 6.97];
        foreach ($defaults as $overlay => $expected) {
            $this->assertEqualsWithDelta(
                $expected,
                branding::contrast(self::composite(branding::DEFAULT_BG, $overlay, $white), branding::DEFAULT_INK),
                0.01,
                'The default pair at ' . $overlay . '% over white'
            );
        }
        foreach (self::REFERENCE_COLOURS as $key => $pair) {
            $atdefault = branding::contrast(
                self::composite($pair['bg'], branding::DEFAULT_OVERLAY, $white),
                $pair['ink']
            );
            $this->assertGreaterThan(
                branding::FLOOR,
                $atdefault,
                'At the DEFAULT overlay every audited colour clears the floor even against a white '
                    . 'photograph: ' . $key
            );
        }
        /* And the shape of the gap with a free pair: a pale band with dark ink is certified at
           10.88:1 and reads 3.91:1 over a dark photograph. */
        $this->assertEqualsWithDelta(10.88, branding::contrast('#eeeeee', '#333333'), 0.01);
        $this->assertEqualsWithDelta(
            3.91,
            branding::contrast(self::composite('#eeeeee', 60, '#000000'), '#333333'),
            0.01
        );
    }

    /**
     * A translucent colour over a backdrop, as a browser composites it.
     *
     * The test's own arithmetic on purpose: production composites nothing - the browser does -
     * so a helper in branding.php would be code with no caller.
     *
     * The overlay is an integer percent, not a float alpha. With 0.9, the backdrop's weight
     * (1 - 0.9) is 0.09999999999999998, so the navy's green channel over white comes out a hair
     * under 25.5, which PHP 8.3's round() takes up to 26 and PHP 8.4's down to 25: a ratio of
     * 12.33 on one runtime and 12.40 on the other. (overlay * a + (100 - overlay) * b) / 100
     * lands on exactly 25.5, which every PHP version rounds the same way.
     *
     * @param string $colour The colour painted on top.
     * @param int $overlay Its opacity as the stored percentage, 0-100.
     * @param string $backdrop The colour underneath.
     * @return string The resulting opaque colour, #rrggbb.
     */
    private static function composite(string $colour, int $overlay, string $backdrop): string {
        $top = branding::hex($colour);
        $under = branding::hex($backdrop);
        $out = [];
        for ($i = 1; $i < 7; $i += 2) {
            $a = (int) hexdec(substr($top, $i, 2));
            $b = (int) hexdec(substr($under, $i, 2));
            $out[] = (int) round(($overlay * $a + (100 - $overlay) * $b) / 100);
        }
        return vsprintf('#%02x%02x%02x', $out);
    }
}
