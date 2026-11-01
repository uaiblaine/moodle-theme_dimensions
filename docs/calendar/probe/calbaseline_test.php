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
 * C0 baseline of core's calendar month and day views (docs/calendar/PROPOSAL.md, section 9).
 *
 * A measurement, never shipped: docs/ is export-ignored and phpcs skips this directory. It is not
 * in tests/, so no suite runs it. Copy it into a stack's webserver container and run it there:
 *
 *   docker cp docs/calendar/probe/calbaseline_test.php m503b-webserver-1:/tmp/
 *   docker exec -w /var/www/html m503b-webserver-1 vendor/bin/phpunit /tmp/calbaseline_test.php
 *   docker cp m503b-webserver-1:/tmp/calbaseline.json .
 *
 * The PHPUnit site must be initialised for the mounted code (mdl phpunit-init <stack>).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Baseline of core's calendar month/day views against the planned range service. */
final class calbaseline_test extends \advanced_testcase {
    /** @var string Output file in the container. */
    private const OUT = '/tmp/calbaseline.json';

    public function test_n5(): void {
        $this->run_scenario(5);
    }

    public function test_n25(): void {
        $this->run_scenario(25);
    }

    private function measure(callable $fn): array {
        global $DB;
        $fn();
        $times = [];
        $reads = [];
        $res = null;
        for ($i = 0; $i < 5; $i++) {
            $r0 = $DB->perf_get_reads();
            $t0 = hrtime(true);
            $res = $fn();
            $times[] = (hrtime(true) - $t0) / 1e6;
            $reads[] = $DB->perf_get_reads() - $r0;
        }
        sort($times);
        sort($reads);
        return ['ms' => round($times[2], 1), 'reads' => $reads[2], 'res' => $res];
    }

    private function ws(string $name, array $args): array {
        $_POST['sesskey'] = sesskey();
        $r = \core_external\external_api::call_external_function($name, $args);
        if ($r['error']) {
            throw new \Exception(json_encode($r));
        }
        return json_decode(json_encode($r['data']), true);
    }

    private function stats(array $m, ?array $extra = null): array {
        $d = $m['res'];
        $json = json_encode($d);
        $ids = [];
        $entries = 0;
        $courses = [];
        foreach ($d['weeks'] ?? [] as $w) {
            foreach ($w['days'] as $day) {
                foreach ($day['events'] as $e) {
                    $entries++;
                    $ids[$e['id']] = 1;
                    $courses[(int)($e['course']['id'] ?? 0)] = 1;
                }
            }
        }
        return ['reads' => $m['reads'], 'ms' => $m['ms'], 'raw' => strlen($json), 'gzip' => strlen(gzencode($json)),
            'entries' => $entries, 'distinct' => count($ids), 'ids' => array_keys($ids), 'courseids' => array_keys($courses),
            'filter_selector_bytes' => strlen($d['filter_selector'] ?? '')] + ($extra ?? []);
    }

    private function run_scenario(int $n): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $tz = \core_date::get_user_timezone_object(99);
        $day = fn(int $d, int $h = 9) => (new \DateTime('2026-11-' . sprintf('%02d', $d) . sprintf(' %02d:00', $h), $tz))->getTimestamp();

        $parent = $gen->create_category(['name' => 'Parent']);
        $subs = [$gen->create_category(['name' => 'Sub1', 'parent' => $parent->id]),
            $gen->create_category(['name' => 'Sub2', 'parent' => $parent->id])];
        $cats = [$parent, $subs[0], $subs[1]];
        $learner = $gen->create_user();
        $bycat = [];
        $courseids = [];
        $multi10 = 0;
        for ($i = 0; $i < $n; $i++) {
            $cat = $cats[$i % 3];
            $c = $gen->create_course(['category' => $cat->id, 'fullname' => "Course $i"]);
            $courseids[] = $c->id;
            $bycat[$cat->id][] = $c->id;
            $gen->enrol_user($learner->id, $c->id, 'student');
            $g = $gen->create_group(['courseid' => $c->id]);
            $gen->create_group_member(['groupid' => $g->id, 'userid' => $learner->id]);
            for ($k = 0; $k < 6; $k++) {
                $gen->create_event(['name' => "T$i-$k", 'eventtype' => 'course', 'courseid' => $c->id,
                    'timestart' => $day(2 + (($k * 4 + $i) % 27), 10), 'timeduration' => 3600]);
            }
            $gen->create_event(['name' => "M3-$i", 'eventtype' => 'course', 'courseid' => $c->id,
                'timestart' => $day(2 + $i % 10), 'timeduration' => 3 * DAYSECS]);
            $e10 = $gen->create_event(['name' => "M10-$i", 'eventtype' => 'course', 'courseid' => $c->id,
                'timestart' => $day(4 + $i % 3), 'timeduration' => 10 * DAYSECS]);
            $multi10 = $multi10 ?: $e10->id;
            $gen->create_event(['name' => "G$i", 'eventtype' => 'group', 'courseid' => $c->id, 'groupid' => $g->id,
                'timestart' => $day(12 + $i % 10, 14), 'timeduration' => 3600]);
            for ($a = 0; $a < 2; $a++) {
                $gen->create_module('assign', ['course' => $c->id, 'name' => "A$i-$a",
                    'duedate' => $day($a ? 20 + $i % 8 : 10 + $i % 10, 23)]);
            }
        }
        $gen->create_event(['name' => 'Site1', 'eventtype' => 'site', 'timestart' => $day(5)]);
        $gen->create_event(['name' => 'Site2d', 'eventtype' => 'site', 'timestart' => $day(15), 'timeduration' => 2 * DAYSECS]);
        for ($u = 0; $u < 3; $u++) {
            $gen->create_event(['name' => "U$u", 'eventtype' => 'user', 'userid' => $learner->id, 'timestart' => $day(7 + $u, 8)]);
        }
        $gen->create_event(['name' => 'Cat1', 'eventtype' => 'category', 'categoryid' => $parent->id, 'timestart' => $day(18)]);
        $this->setUser($learner);
        $out = ['n' => $n, 'tz' => $tz->getName(), 'dbfamily' => $DB->get_dbfamily(), 'parent' => $parent->id,
            'subs' => [$subs[0]->id, $subs[1]->id], 'bycat' => $bycat, 'multi10id' => $multi10];

        // A: month, whole site.
        $m = $this->measure(fn() => $this->ws('core_calendar_get_calendar_monthly_view', ['year' => 2026, 'month' => 11, 'courseid' => SITEID, 'categoryid' => null, 'includenavigation' => true, 'mini' => false, 'day' => 1, 'view' => 'month']));
        $out['a'] = $this->stats($m);
        $one = null;
        foreach ($m['res']['weeks'] as $w) {
            foreach ($w['days'] as $dd) {
                foreach ($dd['events'] as $e) {
                    if ((int)$e['id'] === $multi10) {
                        $one = $one ?? strlen(json_encode($e));
                    }
                }
            }
        }
        $out['a']['one_10day_event_json'] = $one;
        $ev = $m['res']['weeks'][1]['days'][3]['events'][0] ?? null;
        $sizes = [];
        foreach ((array)$ev as $k => $v) {
            $sizes[$k] = strlen(json_encode($v));
        }
        arsort($sizes);
        $out['a']['event_key_bytes'] = array_slice($sizes, 0, 8, true);
        $sub = [];
        foreach ((array)($ev['course'] ?? []) as $k => $v) {
            $sub[$k] = strlen(json_encode($v));
        }
        arsort($sub);
        $out['a']['course_key_bytes'] = array_slice($sub, 0, 5, true);
        $out['a']['day_keys'] = array_keys($m['res']['weeks'][1]['days'][3]);
        // B: month restricted to the parent category.
        $m = $this->measure(fn() => $this->ws('core_calendar_get_calendar_monthly_view', ['year' => 2026, 'month' => 11, 'courseid' => SITEID, 'categoryid' => $parent->id, 'includenavigation' => true, 'mini' => false, 'day' => 1, 'view' => 'month']));
        $out['b'] = $this->stats($m);
        $inb = array_flip($out['b']['courseids']);
        $out['b']['courses_in_parent'] = count(array_intersect_key($inb, array_flip($bycat[$parent->id] ?? [])));
        $out['b']['courses_in_sub1'] = count(array_intersect_key($inb, array_flip($bycat[$subs[0]->id] ?? [])));
        $out['b']['courses_in_sub2'] = count(array_intersect_key($inb, array_flip($bycat[$subs[1]->id] ?? [])));
        // C: busiest day of the whole-site month.
        $best = [0, 0];
        foreach ($m['res']['weeks'] ?? [] as $w) {
            unset($w);
        }
        $mm = $this->ws('core_calendar_get_calendar_monthly_view', ['year' => 2026, 'month' => 11, 'courseid' => SITEID, 'categoryid' => null, 'includenavigation' => true, 'mini' => false, 'day' => 1, 'view' => 'month']);
        foreach ($mm['weeks'] as $w) {
            foreach ($w['days'] as $dd) {
                if ((int)date('n', (int)$dd['timestamp']) === 11 && count($dd['events']) > $best[1]) {
                    $best = [(int)$dd['mday'], count($dd['events'])];
                }
            }
        }
        $m = $this->measure(fn() => $this->ws('core_calendar_get_calendar_day_view', ['year' => 2026, 'month' => 11, 'day' => $best[0], 'courseid' => SITEID, 'categoryid' => null]));
        $out['c'] = ['day' => $best[0], 'events_in_month_cell' => $best[1], 'reads' => $m['reads'], 'ms' => $m['ms'],
            'raw' => strlen(json_encode($m['res'])), 'gzip' => strlen(gzencode(json_encode($m['res']))),
            'entries' => count($m['res']['events'] ?? [])];

        // D: the planned floor, api::get_events over 42 days with calendar_get_view's sets.
        require_once($CFG->dirroot . '/calendar/lib.php');
        $dow = (int)(new \DateTime('2026-11-01', $tz))->format('w');
        $off = ($dow - calendar_get_starting_weekday() + 7) % 7;
        $first = (new \DateTime('2026-11-01 00:00', $tz))->modify("-$off days");
        $tstart = $first->getTimestamp();
        $tend = (clone $first)->modify('+42 days')->modify('-1 second')->getTimestamp();
        $fn = function () use ($tstart, $tend) {
            $cal = \calendar_information::create($tstart, SITEID);
            [$u, $g, $c, $k] = array_map(fn($p) => $p === true ? null : ($p === false ? [] : (is_array($p) ? $p : [$p])),
                [$cal->users, $cal->groups, $cal->courses, $cal->categories]);
            $events = \core_calendar\local\api::get_events($tstart, $tend, null, null, null, null, 0, null, $u, $g, $c, $k, true, true,
                function ($event) {
                    if ($proxy = $event->get_course_module()) {
                        return $proxy->get_proxied_instance()->uservisible;
                    }
                    if ($proxy = $event->get_category()) {
                        return $proxy->get_proxied_instance()->is_uservisible();
                    }
                    return true;
                });
            $slim = [];
            $dict = [];
            foreach ($events as $e) {
                $t = $e->get_times();
                $course = $e->get_course();
                $cid = $course ? (int)$course->get('id') : 0;
                $cm = $e->get_course_module();
                $slim[] = ['id' => $e->get_id(), 'name' => $e->get_name(), 'eventtype' => $e->get_type(),
                    'timestart' => $t->get_start_time()->getTimestamp(),
                    'timeduration' => $t->get_end_time()->getTimestamp() - $t->get_start_time()->getTimestamp(),
                    'courseid' => $cid, 'categoryid' => $e->get_category() ? (int)$e->get_category()->get('id') : 0,
                    'groupid' => $e->get_group() ? (int)$e->get_group()->get('id') : 0,
                    'modulename' => $cm ? $cm->get('modname') : null, 'instance' => $cm ? (int)$cm->get('instance') : null,
                    'location' => $e->get_location()];
                if ($cid > 1 && !isset($dict[$cid])) {
                    $dict[$cid] = ['id' => $cid, 'fullname' => $course->get('fullname'), 'categoryid' => (int)$course->get('category')];
                }
            }
            return ['events' => $slim, 'courses' => array_values($dict)];
        };
        $m = $this->measure($fn);
        $j = json_encode($m['res']);
        $out['d'] = ['reads' => $m['reads'], 'ms' => $m['ms'], 'from' => $first->format('Y-m-d'), 'entries' => count($m['res']['events']),
            'raw' => strlen($j), 'gzip' => strlen(gzencode($j)), 'events_json' => strlen(json_encode($m['res']['events'])),
            'courses_json' => strlen(json_encode($m['res']['courses']))];
        $out['d']['ids_vs_month_only_in_d'] = array_values(array_diff(array_column($m['res']['events'], 'id'), $out['a']['ids']));
        $out['d']['ids_vs_d_only_in_month'] = array_values(array_diff($out['a']['ids'], array_column($m['res']['events'], 'id')));
        unset($out['a']['ids'], $out['b']['ids']);

        $all = file_exists(self::OUT) ? json_decode(file_get_contents(self::OUT), true) : [];
        $all["n$n"] = $out;
        file_put_contents(self::OUT, json_encode($all, JSON_PRETTY_PRINT));
        $this->assertNotEmpty($out);
    }
}
