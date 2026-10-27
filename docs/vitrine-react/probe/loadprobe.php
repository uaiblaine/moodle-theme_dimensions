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
 * The showcase's load probe and server meter (R0 of docs/vitrine-react/PLAN.md, section 5.4).
 *
 * A measurement script, never shipped: docs/ is export-ignored and phpcs skips this directory.
 * Two jobs:
 *
 * --seed builds (idempotently) a category subtree of a realistic size on a DEV stack: a root, ten
 * areas, leaves under them, N courses round-robin over the leaves, every course carrying a landing
 * page model, a modality and a format, a cover on about a third of them, a quarter of them PUBLIC,
 * a few unlisted and a few core-hidden, enrol instances of every shape the classifier reads, a
 * student and a manager, and a branding row with a hero image on the root. The root and the first
 * five areas are public, so the visitor's page has something to draw.
 *
 * --run measures the REAL entries, not a reconstruction of them: the logged-in shell's
 * course_renderer::course_category() plus the hero full_header() draws, as the seeded student, the
 * manager or the admin; and the visitor's request::handle() plus the render of its listing. Each
 * warm (one uncounted pass first, so the shared caches are hot) or --cold (purge_all_caches()
 * first). It prints statements (perf_get_reads()), wall ms, peak KB, the HTML bytes of the listing
 * and of the hero, and the AMD modules and strings_for_js the page asked for - as a table, or as
 * JSON with --json.
 *
 * Probe users get a random password that is never stored in clear and never printed: nobody logs
 * in as them; the meter switches to them with set_user().
 *
 * Run it from the stack's webserver container. The checkout mounted there is the live one, so a
 * copy of this file is placed under /tmp and run from there; the config is found by walking up from
 * this file to the Moodle root, else from MOODLE_CONFIG, else /var/www/html/config.php:
 *
 *   docker cp docs/vitrine-react/probe/loadprobe.php m503b-webserver-1:/tmp/loadprobe.php
 *   docker exec -u www-data m503b-webserver-1 php /tmp/loadprobe.php --seed --courses=60 --leaves=6
 *   docker exec -u www-data m503b-webserver-1 php /tmp/loadprobe.php --run --viewer=student
 *   docker exec -u www-data m503b-webserver-1 php /tmp/loadprobe.php --run --viewer=visitor --cold --json
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

/**
 * The site's config.php: walked up from here (skipping the theme's own config.php), else the environment, else the stack's.
 *
 * @return string
 */
function probe_config_path(): string {
    $dir = __DIR__;
    while ($dir !== dirname($dir)) {
        $dir = dirname($dir);
        if (is_file($dir . '/config.php') && (is_file($dir . '/lib/setup.php') || is_file($dir . '/public/lib/setup.php'))) {
            return $dir . '/config.php';
        }
    }
    return getenv('MOODLE_CONFIG') ?: '/var/www/html/config.php';
}

require(probe_config_path());
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/cohort/lib.php');

use core\context_helper;
use local_unlistedcourses\access;
use local_unlistedcourses\category_access;
use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use theme_dimensions\local\category\branding;
use theme_dimensions\local\category\request;
use theme_dimensions\local\landingfields;
use theme_dimensions\output\category_hero;

[$options, $unrecognised] = cli_get_params(
    [
        'seed' => false,
        'run' => false,
        'viewer' => 'student',
        'subtree' => 'root',
        'sort' => '',
        'perpage' => 0,
        'cold' => false,
        'json' => false,
        'courses' => 5000,
        'leaves' => 200,
        'help' => false,
    ],
    ['h' => 'help']
);

if ($options['help'] || (!$options['seed'] && !$options['run'])) {
    echo "Showcase load probe. --seed builds the fixture (idempotent); --run measures the real entries.\n";
    echo "  --seed [--courses=5000] [--leaves=200]\n";
    echo "  --run --viewer=student|manager|admin|visitor --subtree=root|mid|leaf|site [--sort=name|recent|category]\n";
    echo "        [--perpage=N] [--cold] [--json]\n";
    echo "  site = the course index (category 0); the visitor's site root needs categoryrootpublic.\n";
    exit(0);
}

const PROBE_ROOT_IDNUMBER = 'vitrine_probe_root';
const PROBE_MIDS = 10;
const PROBE_PUBLIC_MIDS = 5;

/**
 * Print one line, flushed, with a timestamp (to STDERR under --json, so STDOUT stays one document).
 *
 * @param string $line The line.
 * @return void
 */
function probe_say(string $line): void {
    global $options;
    $out = date('H:i:s') . '  ' . $line . "\n";
    if (!empty($options['json'])) {
        fwrite(STDERR, $out);
    } else {
        echo $out;
        flush();
    }
}

/**
 * A small JPEG of one colour, made by GD: a cover or a hero image that costs the stack nothing to store.
 *
 * @param int $width Width.
 * @param int $height Height.
 * @param int $seed Picks the colour.
 * @return string The JPEG bytes.
 */
function probe_jpeg(int $width, int $height, int $seed): string {
    $image = imagecreatetruecolor($width, $height);
    $colour = imagecolorallocate($image, (37 * $seed) % 200 + 20, (71 * $seed) % 200 + 20, (113 * $seed) % 200 + 20);
    imagefill($image, 0, 0, $colour);
    ob_start();
    imagejpeg($image, null, 70);
    imagedestroy($image);
    return (string) ob_get_clean();
}

/**
 * Seed the fixture: 1 root, 10 areas, the leaves; N courses with their fields, states, covers and enrolments; users.
 *
 * @param int $courses Number of courses.
 * @param int $leaves Number of leaf categories.
 * @return void
 */
function probe_seed(int $courses, int $leaves): void {
    global $DB;

    $admin = get_admin();
    \core\session\manager::set_user($admin);

    $root = $DB->get_record('course_categories', ['idnumber' => PROBE_ROOT_IDNUMBER]);
    if (!$root) {
        $root = core_course_category::create(['name' => 'Showcase load probe', 'idnumber' => PROBE_ROOT_IDNUMBER]);
        probe_say("root category created: {$root->id}");
    } else {
        probe_say("root category exists: {$root->id}");
    }
    $rootid = (int) $root->id;

    // Areas and leaves.
    $midids = [];
    $leafids = [];
    $perleaf = (int) ceil($leaves / PROBE_MIDS);
    for ($m = 0; $m < PROBE_MIDS; $m++) {
        $midnumber = "vitrine_probe_mid_{$m}";
        $mid = $DB->get_record('course_categories', ['idnumber' => $midnumber]);
        if (!$mid) {
            $mid = core_course_category::create(['name' => "Probe area {$m}", 'idnumber' => $midnumber, 'parent' => $rootid]);
        }
        $midids[$m] = (int) $mid->id;
        for ($l = 0; $l < $perleaf && count($leafids) < $leaves; $l++) {
            $leafnumber = "vitrine_probe_leaf_{$m}_{$l}";
            $leaf = $DB->get_record('course_categories', ['idnumber' => $leafnumber]);
            if (!$leaf) {
                $leaf = core_course_category::create([
                    'name' => "Probe unit {$m}.{$l}", 'idnumber' => $leafnumber, 'parent' => $mid->id,
                ]);
            }
            $leafids[] = (int) $leaf->id;
        }
    }
    probe_say('categories ready: ' . count($midids) . ' areas, ' . count($leafids) . ' leaves');

    // Courses, round-robin over the leaves; every 25th is core-hidden; start dates spread around today.
    $existing = $DB->count_records_select('course', $DB->sql_like('shortname', ':s'), ['s' => 'probe\_%']);
    probe_say("courses existing: {$existing} of {$courses}");
    $t0 = microtime(true);
    $today = usergetmidnight(time());
    for ($i = $existing; $i < $courses; $i++) {
        create_course((object) [
            'fullname' => sprintf('Probe course %04d', $i),
            'shortname' => sprintf('probe_%04d', $i),
            'category' => $leafids[$i % count($leafids)],
            'visible' => ($i % 25 === 0) ? 0 : 1,
            'format' => 'topics',
            'numsections' => 1,
            'startdate' => $today + (($i % 90) - 30) * DAYSECS,
            'summary' => '<p>Probe course summary ' . $i . '. A paragraph long enough to fill the card\'s excerpt line '
                . 'the way a real course description does.</p>',
            'summaryformat' => FORMAT_HTML,
        ]);
        if (($i + 1) % 250 === 0) {
            probe_say(sprintf('  %d courses (%.0f ms each)', $i + 1, 1000 * (microtime(true) - $t0) / max(1, $i + 1 - $existing)));
        }
    }
    probe_say('courses ready');

    $courserecs = $DB->get_records_select('course', $DB->sql_like('shortname', ':s'), ['s' => 'probe\_%'],
        'id ASC', 'id, shortname, category, visible');
    $ids = array_map('intval', array_keys($courserecs));

    // Enrol instances: self on 40 % (i % 5 in {0, 1}), guest on 10 % (i % 10 == 2), caps, cohort gates.
    $cohortok = $DB->get_record('cohort', ['idnumber' => 'vitrine_probe_cohort_in']);
    if (!$cohortok) {
        $cohortok = (object) ['contextid' => context_system::instance()->id, 'name' => 'Probe cohort (member)',
            'idnumber' => 'vitrine_probe_cohort_in', 'description' => '', 'descriptionformat' => FORMAT_HTML];
        $cohortok->id = cohort_add_cohort($cohortok);
        $cohortout = (object) ['contextid' => context_system::instance()->id, 'name' => 'Probe cohort (not member)',
            'idnumber' => 'vitrine_probe_cohort_out', 'description' => '', 'descriptionformat' => FORMAT_HTML];
        $cohortout->id = cohort_add_cohort($cohortout);
    } else {
        $cohortout = $DB->get_record('cohort', ['idnumber' => 'vitrine_probe_cohort_out'], '*', MUST_EXIST);
    }
    $self = enrol_get_plugin('self');
    $guest = enrol_get_plugin('guest');
    $n = 0;
    foreach ($courserecs as $rec) {
        $i = (int) substr($rec->shortname, 6);
        $course = $DB->get_record('course', ['id' => $rec->id]);
        if ($i % 5 < 2) {
            $inst = $DB->get_record('enrol', ['courseid' => $rec->id, 'enrol' => 'self']);
            if (!$inst) {
                $instid = $self->add_default_instance($course) ?: $self->add_instance($course);
                $inst = $DB->get_record('enrol', ['id' => $instid]);
            }
            $upd = ['id' => $inst->id, 'status' => ENROL_INSTANCE_ENABLED, 'customint6' => 1];
            if ($i % 50 === 0) {
                $upd['customint3'] = 5;
            }
            if ($i % 25 === 5) {
                $upd['customint5'] = $cohortok->id;
            } else if ($i % 25 === 10) {
                $upd['customint5'] = $cohortout->id;
            }
            $DB->update_record('enrol', (object) $upd);
            $n++;
        }
        if ($i % 10 === 2) {
            $inst = $DB->get_record('enrol', ['courseid' => $rec->id, 'enrol' => 'guest']);
            if (!$inst) {
                $instid = $guest->add_default_instance($course) ?: $guest->add_instance($course);
                $inst = $DB->get_record('enrol', ['id' => $instid]);
            }
            $DB->update_record('enrol', (object) ['id' => $inst->id, 'status' => ENROL_INSTANCE_ENABLED]);
        }
    }
    probe_say("enrol instances ready ({$n} self enabled)");

    // The landing page fields every first paint reads: the model (a seventh are events), the modality, the format.
    landingfields::ensure_fields();
    landingfields::reset_field_cache();
    $values = [
        landingfields::SHORTNAME_MODEL => fn(int $i): int => ($i % 7 === 0) ? landingfields::MODEL_EVENT
            : landingfields::MODEL_COURSE,
        landingfields::SHORTNAME_MODALITY => fn(int $i): int => 1 + ($i % 3),
        landingfields::SHORTNAME_FORMAT => fn(int $i): int => 1 + ($i % 2),
    ];
    foreach ($values as $shortname => $valueof) {
        $field = landingfields::get_field($shortname);
        if (!$field) {
            probe_say("field {$shortname} does not resolve here; skipped");
            continue;
        }
        $have = array_flip(array_map('intval',
            $DB->get_fieldset_select('customfield_data', 'instanceid', 'fieldid = :f', ['f' => $field->get('id')])));
        $rows = [];
        $now = time();
        foreach ($courserecs as $rec) {
            if (isset($have[(int) $rec->id])) {
                continue;
            }
            $value = $valueof((int) substr($rec->shortname, 6));
            $rows[] = (object) [
                'fieldid' => $field->get('id'), 'instanceid' => $rec->id,
                'contextid' => context_course::instance($rec->id)->id,
                'intvalue' => $value, 'value' => (string) $value, 'valueformat' => 0,
                'component' => 'core_course', 'area' => 'course', 'itemid' => 0,
                'timecreated' => $now, 'timemodified' => $now,
            ];
            if (count($rows) >= 500) {
                $DB->insert_records('customfield_data', $rows);
                $rows = [];
            }
        }
        if ($rows) {
            $DB->insert_records('customfield_data', $rows);
        }
        probe_say("field {$shortname} seeded");
    }

    // A cover on about a third of the courses: a small JPEG in core's overview files area.
    $fs = get_file_storage();
    $covers = 0;
    foreach ($courserecs as $rec) {
        $i = (int) substr($rec->shortname, 6);
        if ($i % 3 !== 0) {
            continue;
        }
        $contextid = context_course::instance($rec->id)->id;
        if (!$fs->file_exists($contextid, 'course', 'overviewfiles', 0, '/', 'cover.jpg')) {
            $fs->create_file_from_string([
                'contextid' => $contextid, 'component' => 'course', 'filearea' => 'overviewfiles',
                'itemid' => 0, 'filepath' => '/', 'filename' => 'cover.jpg',
            ], probe_jpeg(480, 270, $i));
        }
        $covers++;
    }
    probe_say("covers ready: {$covers}");

    // Discoverability: about 30 courses unlisted (i % 167 == 3), a quarter public, a few leaves unlisted,
    // the root and the first five areas public.
    $unlisted = 0;
    $public = 0;
    foreach ($courserecs as $rec) {
        $i = (int) substr($rec->shortname, 6);
        $state = discoverability::STATE_DEFAULT;
        if ($i % 167 === 3) {
            $state = discoverability::STATE_UNLISTED;
            $unlisted++;
        } else if ($i % 4 === 1 && (int) $rec->visible === 1) {
            $state = discoverability::STATE_PUBLIC;
            $public++;
        }
        /* Every course is set, the listed ones included: the site may create courses unlisted
           (local_unlistedcourses' creation default), and a probe of a mostly unlisted catalogue
           measures the plugin's probe cap rather than the showcase. */
        if (discoverability::get_state((int) $rec->id) !== $state) {
            discoverability::set_state((int) $rec->id, $state, (int) $admin->id);
        }
    }
    $unlistedleaves = array_values(array_filter([7, 77, 177], fn(int $k): bool => isset($leafids[$k])));
    if (!$unlistedleaves && count($leafids) > 2) {
        $unlistedleaves = [count($leafids) - 1];
    }
    foreach ($unlistedleaves as $k) {
        if (category_discoverability::get_state($leafids[$k]) !== category_discoverability::STATE_UNLISTED) {
            category_discoverability::set_state($leafids[$k], category_discoverability::STATE_UNLISTED, (int) $admin->id);
        }
    }
    foreach (array_merge([$rootid], array_slice($midids, 0, PROBE_PUBLIC_MIDS)) as $catid) {
        if (category_discoverability::get_state($catid) !== category_discoverability::STATE_PUBLIC) {
            category_discoverability::set_state($catid, category_discoverability::STATE_PUBLIC, (int) $admin->id);
        }
    }
    probe_say("discoverability: {$public} courses public, {$unlisted} unlisted, " . count($unlistedleaves)
        . ' leaves unlisted; the root and ' . PROBE_PUBLIC_MIDS . ' areas public');

    // The root's branding: the image layout, a tagline and a public description, and a hero picture.
    if (!$DB->record_exists(branding::TABLE, ['categoryid' => $rootid])) {
        branding::save($rootid, (object) [
            'layout' => branding::LAYOUT_IMAGE,
            'tagline' => 'Every course of the load probe, in one place',
            'publicsummary' => 'The load probe of the category showcase.',
            'publicdescription' => '<p>The showcase\'s load probe: a subtree of realistic size for the first paint.</p>',
            'publicdescriptionformat' => FORMAT_HTML,
        ]);
    }
    $rootcontext = context_coursecat::instance($rootid);
    if (!$fs->get_area_files($rootcontext->id, branding::COMPONENT, branding::AREA_HERO, 0, 'filename', false)) {
        $fs->create_file_from_string([
            'contextid' => $rootcontext->id, 'component' => branding::COMPONENT, 'filearea' => branding::AREA_HERO,
            'itemid' => 0, 'filepath' => '/', 'filename' => 'hero.jpg',
        ], probe_jpeg(1600, 600, 7));
    }
    branding::forget_all();
    probe_say('root branding ready (image layout, hero picture)');

    // Users: a student enrolled in about thirty courses (one hidden, one unlisted), in the "in" cohort, with five
    // favourites; a manager at the root. A random password each, never stored in clear and never printed.
    $users = [];
    foreach (['probe_student' => ['Probe', 'Student'], 'probe_manager' => ['Probe', 'Manager']] as $username => $names) {
        $user = $DB->get_record('user', ['username' => $username]);
        if (!$user) {
            $user = create_user_record($username, random_string(32));
            $DB->update_record('user', (object) ['id' => $user->id, 'firstname' => $names[0], 'lastname' => $names[1],
                'email' => $username . '@example.invalid', 'confirmed' => 1]);
        }
        $users[$username] = $user;
    }
    $student = $users['probe_student'];
    $managerrole = $DB->get_field('role', 'id', ['shortname' => 'manager']);
    role_assign($managerrole, $users['probe_manager']->id, $rootcontext->id);
    if (!cohort_is_member($cohortok->id, $student->id)) {
        cohort_add_member($cohortok->id, $student->id);
    }
    $manual = enrol_get_plugin('manual');
    $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
    $enrolled = 0;
    foreach ($courserecs as $rec) {
        $i = (int) substr($rec->shortname, 6);
        $mine = ($i % 167 === 3 && $i < 400) || ($i % 25 === 0 && $i < 100) || ($i % 170 === 9) || ($courses < 340 && $i % 10 === 9);
        if (!$mine) {
            continue;
        }
        $inst = $DB->get_record('enrol', ['courseid' => $rec->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        if (!$DB->record_exists('user_enrolments', ['enrolid' => $inst->id, 'userid' => $student->id])) {
            $manual->enrol_user($inst, $student->id, $studentrole);
        }
        $enrolled++;
    }
    $service = \core_favourites\service_factory::get_service_for_user_context(context_user::instance($student->id));
    $favs = 0;
    foreach (array_slice($ids, min(100, intdiv(count($ids), 2)), 5) as $cid) {
        $ctx = context_course::instance($cid);
        if (!$service->favourite_exists('core_course', 'courses', $cid, $ctx)) {
            $service->create_favourite('core_course', 'courses', $cid, $ctx);
        }
        $favs++;
    }
    probe_say("users ready: student enrolled in {$enrolled}, {$favs} favourites; manager at the root");
    probe_say('seed done');
}

/**
 * The category a run measures.
 *
 * @param string $which root|mid|leaf|site
 * @return int Category id; 0 for the course index.
 */
function probe_subtree(string $which): int {
    global $DB;
    if ($which === 'site') {
        return 0;
    }
    $map = ['root' => PROBE_ROOT_IDNUMBER, 'mid' => 'vitrine_probe_mid_3', 'leaf' => 'vitrine_probe_leaf_3_0'];
    return (int) $DB->get_field('course_categories', 'id', ['idnumber' => $map[$which] ?? $map['root']], MUST_EXIST);
}

/**
 * Forget every per-request memo a new request starts without.
 *
 * @return void
 */
function probe_forget(): void {
    global $FILTERLIB_PRIVATE;
    access::reset_caches();
    category_access::reset_caches();
    discoverability::reset_caches();
    category_discoverability::reset_caches();
    context_helper::reset_caches();
    category_hero::reset();
    \theme_dimensions\external\get_public_category_rows::reset();
    $FILTERLIB_PRIVATE = null;
    \filter_manager::reset_caches();
}

/**
 * A protected property of the page's requirements manager, read by reflection.
 *
 * @param string $name The property.
 * @return mixed
 */
function probe_requires(string $name) {
    global $PAGE;
    $property = new ReflectionProperty($PAGE->requires, $name);
    return $property->getValue($PAGE->requires);
}

/**
 * What the page asked the browser for at load: AMD modules (one per require() call) and strings_for_js.
 *
 * @return array [modules => string[], strings => int]
 */
function probe_page_asks(): array {
    $modules = [];
    foreach ((array) probe_requires('amdjscode') as $code) {
        if (preg_match_all("/require\(\['([^']+)'\]/", (string) $code, $matches)) {
            foreach ($matches[1] as $module) {
                $modules[] = $module;
            }
        }
    }
    $strings = 0;
    foreach ((array) probe_requires('stringsforjs') as $component => $ids) {
        $strings += count($ids);
    }
    return ['modules' => $modules, 'strings' => $strings];
}

/**
 * Set the query string a page reads with optional_param().
 *
 * @param array $query name => value.
 * @return void
 */
function probe_query(array $query): void {
    $_GET = [];
    foreach ($query as $name => $value) {
        $_GET[$name] = $value;
    }
}

/**
 * One request of the logged-in shell: what course/index.php does before the header, then the hero the header draws.
 *
 * @param int $categoryid The category, 0 for the course index.
 * @param array $query The query string.
 * @return array [listing html, hero html, page asks]
 */
function probe_logged_in_request(int $categoryid, array $query): array {
    global $PAGE, $OUTPUT;
    probe_query(['categoryid' => $categoryid] + $query);
    $PAGE = new moodle_page();
    if ($categoryid) {
        $PAGE->set_category_by_id($categoryid);
    } else {
        $PAGE->set_context(context_system::instance());
    }
    $PAGE->set_url('/course/index.php', ['categoryid' => $categoryid] + $query);
    $PAGE->set_pagelayout('coursecategory');
    $PAGE->set_pagetype('course-index-category');
    $PAGE->force_theme('dimensions');
    $OUTPUT = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
    $courserenderer = $PAGE->get_renderer('core', 'course', RENDERER_TARGET_GENERAL);
    $listing = $courserenderer->course_category($categoryid);
    probe_mark();
    $hero = $OUTPUT->full_header();
    return [$listing, $hero, probe_page_asks()];
}

/**
 * One request of the visitor's shell: request::handle(), then the render of its listing and of the hero.
 *
 * @param int $categoryid The category, 0 for the site root.
 * @param array $query The query string.
 * @return array [listing html, hero html, page asks]
 */
function probe_visitor_request(int $categoryid, array $query): array {
    global $PAGE, $OUTPUT;
    probe_query(['id' => $categoryid] + $query);
    $PAGE = new moodle_page();
    $answer = request::handle($categoryid);
    if ($answer->redirect !== null) {
        throw new moodle_exception('generalexceptionmessage', 'error', '', 'The visitor was redirected to '
            . $answer->redirect->out(false) . ': is the category public?');
    }
    $OUTPUT = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
    $listing = $OUTPUT->render($answer->page);
    probe_mark();
    $hero = $OUTPUT->full_header();
    return [$listing, $hero, probe_page_asks()];
}

/**
 * Note the statements spent so far, between the listing and the hero.
 *
 * @param bool $reset Hand the mark back and forget it, instead of setting it.
 * @return int|null The reads at the mark, null when none was set.
 */
function probe_mark(bool $reset = false): ?int {
    global $DB;
    static $mark = null;
    $was = $mark;
    $mark = $reset ? null : (int) $DB->perf_get_reads();
    return $reset ? $was : $mark;
}

/**
 * Meter one request: statements, wall time, peak memory, the bytes it drew and what it asked for.
 *
 * @param callable $request Returns [listing, hero, asks].
 * @return array The measurement.
 */
function probe_meter(callable $request): array {
    global $DB;
    probe_forget();
    probe_mark(true);
    memory_reset_peak_usage();
    $m0 = memory_get_usage();
    $r0 = $DB->perf_get_reads();
    $t0 = microtime(true);
    [$listing, $hero, $asks] = $request();
    $ms = 1000 * (microtime(true) - $t0);
    $reads = $DB->perf_get_reads() - $r0;
    $mark = probe_mark(true);
    return [
        'reads' => (int) $reads,
        'listingreads' => $mark === null ? null : $mark - (int) $r0,
        'heroreads' => $mark === null ? null : (int) $DB->perf_get_reads() - $mark,
        'ms' => round($ms, 1),
        'peakkb' => (int) round((memory_get_peak_usage() - $m0) / 1024),
        'listingbytes' => strlen($listing),
        'herobytes' => strlen($hero),
        'cards' => substr_count($listing, 'data-region="dim-card"'),
        'posters' => substr_count($listing, 'data-region="dim-poster-control"'),
        'amdmodules' => count($asks['modules']),
        'modules' => $asks['modules'],
        'stringsforjs' => $asks['strings'],
    ];
}

if ($options['seed']) {
    probe_seed((int) $options['courses'], (int) $options['leaves']);
}

if ($options['run']) {
    $viewer = (string) $options['viewer'];
    $categoryid = probe_subtree((string) $options['subtree']);
    $query = [];
    if ($options['sort'] !== '') {
        $query['sort'] = (string) $options['sort'];
    }
    if ((int) $options['perpage'] > 0) {
        $query['perpage'] = (int) $options['perpage'];
    }

    if ($viewer === 'visitor') {
        \core\session\manager::init_empty_session();
        $request = fn() => probe_visitor_request($categoryid, $query);
        $entry = 'request::handle()';
    } else {
        $username = ['student' => 'probe_student', 'manager' => 'probe_manager', 'admin' => 'admin'][$viewer] ?? null;
        if ($username === null) {
            cli_error("Unknown viewer '{$viewer}'.");
        }
        \core\session\manager::set_user($DB->get_record('user', ['username' => $username], '*', MUST_EXIST));
        $request = fn() => probe_logged_in_request($categoryid, $query);
        $entry = 'course_renderer::course_category()';
    }

    if ($options['cold']) {
        purge_all_caches();
    } else {
        // A warm-up pass, uncounted, so the shared caches (MUC, the theme, the templates) are hot.
        probe_meter($request);
    }
    $result = probe_meter($request);
    $report = [
        'probe' => 'loadprobe',
        'measured' => date('c'),
        'site' => $CFG->wwwroot,
        'entry' => $entry,
        'viewer' => $viewer,
        'subtree' => (string) $options['subtree'],
        'categoryid' => $categoryid,
        'query' => (object) $query,
        'mode' => $options['cold'] ? 'cold' : 'warm',
        'xdebug' => extension_loaded('xdebug'),
        'cachejs' => !empty($CFG->cachejs),
        'courses in subtree' => $categoryid
            ? (int) $DB->count_records_sql(
                'SELECT COUNT(1) FROM {course} c JOIN {course_categories} cc ON cc.id = c.category
                  WHERE cc.id = :id OR ' . $DB->sql_like('cc.path', ':path'),
                ['id' => $categoryid, 'path' => '%/' . $categoryid . '/%']
            )
            : (int) $DB->count_records('course') - 1,
    ] + $result;

    if ($options['json']) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    } else {
        probe_say("run: {$entry} viewer={$viewer} subtree={$options['subtree']} (category {$categoryid}) "
            . strtoupper($report['mode']) . ($query ? ' ' . http_build_query($query, '', '&') : ''));
        printf("%-10s %6s %9s %9s %12s %10s %6s %8s %6s %8s\n",
            'mode', 'reads', 'ms', 'peak KB', 'listing B', 'hero B', 'cards', 'posters', 'amd', 'strings');
        printf("%-10s %6d %9.1f %9d %12d %10d %6d %8d %6d %8d\n", $report['mode'], $result['reads'], $result['ms'],
            $result['peakkb'], $result['listingbytes'], $result['herobytes'], $result['cards'], $result['posters'],
            $result['amdmodules'], $result['stringsforjs']);
        echo 'modules: ' . implode(', ', $result['modules']) . "\n";
        echo 'courses in subtree: ' . $report['courses in subtree'] . ($report['xdebug'] ? ' (Xdebug loaded)' : '') . "\n";
    }
}
