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
 * The related contents' demo data (R1 of docs/related/PLAN.md), for a DEV stack.
 *
 * A demonstration script, never shipped: docs/ is export-ignored and phpcs skips this directory.
 * --seed builds, idempotently, a category of nine courses with landing pages, one competency framework of six
 * competencies and the links between them, so that the landing page of "Liderança situacional" draws a band with
 * every tier of the ranking and every refusal a reader meets:
 *
 *   course                      competencies           what its row on the parent's band shows
 *   Liderança situacional       LID01 LID02 LID03      the parent
 *   Liderança na prática        LID01 LID02 LID03      tier 1, parity
 *   Comunicação assertiva       LID02 (complete)       tier 2, completing it grants a shared competency
 *   Mediação de conflitos       LID03 LID04            tier 3, half its set shared
 *   Atendimento ao público      LID02 LID06            tier 3, half its set shared
 *   Planejamento estratégico    LID04                  not related to the parent (related to Mediação)
 *   Primeiros socorros          LID05                  not related to anything
 *   Liderança (oculto)          LID01                  hidden: never related (D11)
 *   Liderança (não listada)     LID01 LID02            unlisted: an account with a way in, never a visitor
 *
 * Every course but the unlisted one is PUBLIC, so the visitor's band has the same rows minus that one. It enables
 * core's competencies, which the band needs and a fresh stack leaves on, and says so if they were off.
 * --reset deletes what --seed made (the courses, the category, the framework), found by their idnumbers.
 *
 * Run it from the stack's webserver container, as a copy, because the mounted checkout is the live one:
 *
 *   docker cp docs/related/demo/seed.php m503-webserver-1:/tmp/relatedseed.php
 *   docker exec -u www-data m503-webserver-1 php /tmp/relatedseed.php --seed
 *   docker exec -u www-data m503-webserver-1 php /tmp/relatedseed.php --reset
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
function relatedseed_config_path(): string {
    $dir = __DIR__;
    while ($dir !== dirname($dir)) {
        $dir = dirname($dir);
        if (is_file($dir . '/config.php') && (is_file($dir . '/lib/setup.php') || is_file($dir . '/public/lib/setup.php'))) {
            return $dir . '/config.php';
        }
    }
    return getenv('MOODLE_CONFIG') ?: '/var/www/html/config.php';
}

require(relatedseed_config_path());
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');

use core_competency\api;
use core_competency\competency_framework;
use core_competency\course_competency;
use local_unlistedcourses\discoverability;
use theme_dimensions\local\landingfields;

/** The idnumber of the category and the framework, and the prefix of every course's shortname and idnumber. */
const RELATEDSEED_ID = 'DIMREL';

/** The competencies: idnumber => name. */
const RELATEDSEED_COMPETENCIES = [
    'LID01' => 'Liderança de equipes',
    'LID02' => 'Comunicação',
    'LID03' => 'Gestão de conflitos',
    'LID04' => 'Planejamento',
    'LID05' => 'Primeiros socorros',
    'LID06' => 'Atendimento ao público',
];

/**
 * The courses, in the table's order: key => [name, competencies (idnumber => outcome), state, visible, start offset in days].
 */
const RELATEDSEED_COURSES = [
    '01' => ['Liderança situacional', ['LID01' => 0, 'LID02' => 0, 'LID03' => 0], 'public', 1, 10],
    '02' => ['Liderança na prática', ['LID01' => 0, 'LID02' => 0, 'LID03' => 0], 'public', 1, 40],
    '03' => ['Comunicação assertiva', ['LID02' => course_competency::OUTCOME_COMPLETE], 'public', 1, 20],
    '04' => ['Mediação de conflitos', ['LID03' => 0, 'LID04' => 0], 'public', 1, 5],
    '05' => ['Atendimento ao público', ['LID02' => 0, 'LID06' => 0], 'public', 1, -30],
    '06' => ['Planejamento estratégico', ['LID04' => 0], 'public', 1, 15],
    '07' => ['Primeiros socorros', ['LID05' => 0], 'public', 1, 25],
    '08' => ['Liderança (oculto)', ['LID01' => 0], 'public', 0, 12],
    '09' => ['Liderança (não listada)', ['LID01' => 0, 'LID02' => 0], 'unlisted', 1, 8],
];

[$options, $unrecognised] = cli_get_params(['seed' => false, 'reset' => false, 'help' => false], ['h' => 'help']);
if ($options['help'] || $options['seed'] === $options['reset']) {
    echo "The related contents' demo data. --seed builds it (idempotent); --reset deletes it.\n";
    exit($options['help'] ? 0 : 1);
}

\core\session\manager::set_user(get_admin());
if ($options['reset']) {
    relatedseed_reset();
} else {
    relatedseed_seed();
}

/**
 * Build the category, the framework and the courses, each only when its idnumber is not taken yet.
 *
 * @return void
 */
function relatedseed_seed(): void {
    global $DB;

    if (!api::is_enabled()) {
        set_config('enabled', 1, 'core_competency');
        echo "Competencies were disabled on this site: enabled.\n";
    }
    landingfields::ensure_fields();

    $category = $DB->get_record('course_categories', ['idnumber' => RELATEDSEED_ID]);
    if (!$category) {
        $category = core_course_category::create(['name' => 'Demonstração: conteúdo relacionado', 'idnumber' => RELATEDSEED_ID]);
    }
    $competencies = relatedseed_framework();

    foreach (RELATEDSEED_COURSES as $key => [$name, $links, $state, $visible, $days]) {
        $shortname = RELATEDSEED_ID . '-' . $key;
        $course = $DB->get_record('course', ['shortname' => $shortname]);
        if (!$course) {
            $course = create_course((object) [
                'category' => $category->id,
                'fullname' => $name,
                'shortname' => $shortname,
                'idnumber' => $shortname,
                'visible' => $visible,
                'startdate' => usergetmidnight(time()) + $days * DAYSECS,
                'summary' => '<p>Curso de demonstração do conteúdo relacionado.</p>',
                'summaryformat' => FORMAT_HTML,
                'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
            ]);
        }
        foreach ($links as $idnumber => $outcome) {
            relatedseed_link((int) $course->id, $competencies[$idnumber], $outcome);
        }
        discoverability::set_state(
            (int) $course->id,
            $state === 'public' ? discoverability::STATE_PUBLIC : discoverability::STATE_UNLISTED
        );
        echo "{$shortname}  id {$course->id}  {$name}\n";
    }
    $parent = $DB->get_field('course', 'id', ['shortname' => RELATEDSEED_ID . '-01']);
    echo "The parent's landing page: " . \theme_dimensions\local\landing\links::page((int) $parent)->out(false) . "\n";
}

/**
 * The framework and its competencies, created where missing.
 *
 * @return int[] Map of competency idnumber => id.
 */
function relatedseed_framework(): array {
    global $DB;

    $framework = competency_framework::get_record(['idnumber' => RELATEDSEED_ID]);
    if (!$framework) {
        $scale = $DB->get_record('scale', ['courseid' => 0, 'name' => get_string('defaultcompetencescale')], 'id', MUST_EXIST);
        $framework = api::create_framework((object) [
            'shortname' => 'Demonstração: conteúdo relacionado',
            'idnumber' => RELATEDSEED_ID,
            'scaleid' => $scale->id,
            'scaleconfiguration' => json_encode([
                ['scaleid' => (string) $scale->id],
                ['id' => 1, 'scaledefault' => 1, 'proficient' => 0],
                ['id' => 2, 'scaledefault' => 0, 'proficient' => 1],
            ]),
            'contextid' => \core\context\system::instance()->id,
            'visible' => 1,
        ]);
    }
    $ids = [];
    foreach (RELATEDSEED_COMPETENCIES as $idnumber => $name) {
        $competency = \core_competency\competency::get_record([
            'competencyframeworkid' => $framework->get('id'),
            'idnumber' => $idnumber,
        ]);
        if (!$competency) {
            $competency = api::create_competency((object) [
                'shortname' => $name,
                'idnumber' => $idnumber,
                'competencyframeworkid' => $framework->get('id'),
            ]);
        }
        $ids[$idnumber] = (int) $competency->get('id');
    }
    return $ids;
}

/**
 * Link a course to a competency with an outcome, unless it already is.
 *
 * @param int $courseid The course.
 * @param int $competencyid The competency.
 * @param int $outcome A course_competency::OUTCOME_* constant, 0 for none.
 * @return void
 */
function relatedseed_link(int $courseid, int $competencyid, int $outcome): void {
    $link = course_competency::get_record(['courseid' => $courseid, 'competencyid' => $competencyid]);
    if (!$link) {
        api::add_competency_to_course($courseid, $competencyid);
        $link = course_competency::get_record(['courseid' => $courseid, 'competencyid' => $competencyid]);
    }
    if ((int) $link->get('ruleoutcome') !== $outcome) {
        api::set_course_competency_ruleoutcome($link, $outcome);
    }
}

/**
 * Delete what --seed made: the courses, the category once empty, and the framework.
 *
 * @return void
 */
function relatedseed_reset(): void {
    global $DB;

    foreach (array_keys(RELATEDSEED_COURSES) as $key) {
        $course = $DB->get_record('course', ['shortname' => RELATEDSEED_ID . '-' . $key]);
        if ($course) {
            delete_course($course, false);
            echo "Deleted course {$course->shortname}.\n";
        }
    }
    $framework = competency_framework::get_record(['idnumber' => RELATEDSEED_ID]);
    if ($framework) {
        api::delete_framework($framework->get('id'));
        echo "Deleted the framework.\n";
    }
    $categoryid = $DB->get_field('course_categories', 'id', ['idnumber' => RELATEDSEED_ID]);
    if ($categoryid) {
        $category = core_course_category::get($categoryid, MUST_EXIST, true);
        if ($category->can_delete_full()) {
            $category->delete_full(false);
            echo "Deleted the category.\n";
        }
    }
    fix_course_sortorder();
}
