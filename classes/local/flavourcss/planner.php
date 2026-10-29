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

namespace theme_dimensions\local\flavourcss;

use theme_dimensions\local\flavourchoice;

/**
 * Theme Dimensions - Decides which flavour sheets are missing and builds each in a PHP process of its own.
 *
 * Boost Union cannot compile two flavours in one process ({@see builder}), and core runs every task of a cron run
 * in the cron process, so each sheet is built by a child running cli/build_flavour_css.php --flavourid. The
 * children run one at a time: every compile takes the theme's single CSS lock, so parallel children would only
 * queue on it. docs/flavourcss/PLAN.md (section 4.3) is the design.
 *
 * A sheet is missing when the localcache file for the current revisions is absent: the file's name carries both
 * revisions, so its absence sees every path that changes a flavour's CSS (a cache reset moves the global revision,
 * a theme build the sub-revision), and none of them has a hook to listen to.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class planner {
    /** @var string The config key of the failure memo: flavour id => the revisions its build failed at. */
    const FAILED = 'flavourcss_failed';

    /** @var int[] The exit codes a child gives when the builder answered; any other code is a failure. */
    const ANSWERS = [builder::BUILT, builder::SKIPPED, builder::PRESENT, builder::BUSY, builder::STALE];

    /** @var \Closure Builds one sheet in a child: fn(int $flavourid, string $direction, \Closure $log): int. */
    private \Closure $runner;

    /** @var \Closure Receives one line of output: fn(string $line): void. */
    private \Closure $log;

    /**
     * Each collaborator defaults to the real one; tests replace them.
     *
     * @param \Closure|null $runner Builds one sheet; default {@see self::run_child()}.
     * @param \Closure|null $log Receives output lines; default mtrace().
     */
    public function __construct(?\Closure $runner = null, ?\Closure $log = null) {
        $this->runner = $runner ?? self::run_child(...);
        $this->log = $log ?? function (string $line): void {
            mtrace($line);
        };
    }

    /**
     * Build every missing sheet, one child at a time.
     *
     * @param bool $dryrun List what would be built, and build nothing.
     * @return array "flavourid/direction" => the child's exit code (or null in a dry run), for each sheet tried.
     */
    public function run(bool $dryrun = false): array {
        global $CFG;

        // The builder refuses these too; asking here spares a child per flavour.
        if (!empty($CFG->themedesignermode) || $CFG->theme !== builder::THEME) {
            return [];
        }

        $todo = $this->missing();
        if (!$todo) {
            return [];
        }
        if (!$dryrun && self::php_binary() === null) {
            ($this->log)('flavourcss: no PHP binary to run a child with ($CFG->pathtophp, or proc_open disabled); nothing built.');
            return [];
        }

        [$rev, $subrev] = builder::revisions();
        $failed = self::failures();
        $results = [];
        foreach ($todo as [$flavourid, $direction]) {
            $key = "{$flavourid}/{$direction}";
            if ($dryrun) {
                ($this->log)("flavourcss: would build flavour {$flavourid} ({$direction})");
                $results[$key] = null;
                continue;
            }
            $start = microtime(true);
            $code = ($this->runner)($flavourid, $direction, $this->log);
            $results[$key] = $code;
            $seconds = microtime(true) - $start;
            ($this->log)(sprintf('flavourcss: flavour %d (%s) exited %d in %.1f s', $flavourid, $direction, $code, $seconds));
            // Anything but the builder's own answers failed: its exception (1), or a fatal error (255).
            if (!in_array($code, self::ANSWERS, true)) {
                $failed[$key] = "{$rev}_{$subrev}";
            } else {
                unset($failed[$key]);
            }
        }
        if (!$dryrun) {
            set_config(self::FAILED, $failed ? json_encode($failed) : null, 'theme_dimensions');
        }

        return $results;
    }

    /**
     * The sheets to build now: buildable flavours, in each direction, without a file for the current revisions.
     *
     * A sheet whose last build failed at the current revisions is left out until a revision moves: a flavour whose
     * SCSS does not compile would otherwise cost a whole compile every minute.
     *
     * @return array Pairs of flavour id and direction, in Boost Union's order.
     */
    public function missing(): array {
        [$rev, $subrev] = builder::revisions();
        $failed = self::failures();
        $todo = [];
        foreach (self::buildable_flavours() as $flavourid) {
            foreach (self::directions() as $direction) {
                if (($failed["{$flavourid}/{$direction}"] ?? null) === "{$rev}_{$subrev}") {
                    continue;
                }
                if (!file_exists(builder::sheet_path($rev, $subrev, $flavourid, $direction))) {
                    $todo[] = [$flavourid, $direction];
                }
            }
        }

        return $todo;
    }

    /**
     * The flavours a page can be served: those applied to categories or cohorts, and those offered for choice.
     *
     * A flavour that applies to nothing is never requested, and building it would cost a compile after every
     * reset for nothing. Boost Union stores the ids as JSON (flavours/edit.php); an applied flag with no ids
     * applies to nothing (flavourslib.php reads the list).
     *
     * @return int[] Flavour ids, in Boost Union's order.
     */
    public static function buildable_flavours(): array {
        global $DB;

        $offered = flavourchoice::selectable_ids();
        $ids = [];
        $fields = 'id, applytocategories, applytocategories_ids, applytocohorts, applytocohorts_ids';
        foreach ($DB->get_records('theme_boost_union_flavours', null, 'sort ASC, id ASC', $fields) as $flavour) {
            $bycategory = !empty($flavour->applytocategories) && !empty(json_decode((string) $flavour->applytocategories_ids));
            $bycohort = !empty($flavour->applytocohorts) && !empty(json_decode((string) $flavour->applytocohorts_ids));
            if ($bycategory || $bycohort || in_array((int) $flavour->id, $offered, true)) {
                $ids[] = (int) $flavour->id;
            }
        }

        return $ids;
    }

    /**
     * The directions a sheet is requested in: right to left only while an installed language reads that way.
     *
     * @return string[] 'ltr', and 'rtl' when needed.
     */
    public static function directions(): array {
        $strings = get_string_manager();
        foreach (array_keys($strings->get_list_of_translations(true)) as $lang) {
            if ($strings->get_string('thisdirection', 'langconfig', null, $lang) === 'rtl') {
                return ['ltr', 'rtl'];
            }
        }

        return ['ltr'];
    }

    /**
     * The PHP to run a child with: this process's own binary on the command line, else core's configured one.
     *
     * Null when there is none, or when proc_open() is disabled: the planner then builds nothing, and the site
     * behaves as it does without it.
     *
     * @return string|null The executable, or null.
     */
    public static function php_binary(): ?string {
        global $CFG;

        if (!function_exists('proc_open')) {
            return null;
        }
        if (CLI_SCRIPT && PHP_BINARY !== '' && is_executable(PHP_BINARY)) {
            return PHP_BINARY;
        }
        // As core's task manager resolves it (find_php_cli_path()).
        if (!empty($CFG->pathtophp) && is_executable(trim($CFG->pathtophp))) {
            return trim($CFG->pathtophp);
        }

        return null;
    }

    /**
     * Build one sheet in a child process, passing each line it prints to the log.
     *
     * @param int $flavourid The flavour.
     * @param string $direction 'ltr' or 'rtl'.
     * @param \Closure $log Receives each output line.
     * @return int The child's exit code: the builder's answer, 1 for its exception, 255 for a fatal error.
     */
    public static function run_child(int $flavourid, string $direction, \Closure $log): int {
        $command = [
            (string) self::php_binary(),
            dirname(__DIR__, 3) . '/cli/build_flavour_css.php',
            '--flavourid=' . $flavourid,
            '--direction=' . $direction,
        ];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
        if (!is_resource($process)) {
            $log("flavourcss: could not start a child for flavour {$flavourid}");
            return 1;
        }
        fclose($pipes[0]);
        while (($line = fgets($pipes[1])) !== false) {
            $log('  ' . rtrim($line, "\n"));
        }
        fclose($pipes[1]);

        return proc_close($process);
    }

    /**
     * The failure memo: "flavourid/direction" => the revisions its last build failed at.
     *
     * @return array The memo.
     */
    private static function failures(): array {
        $stored = json_decode((string) get_config('theme_dimensions', self::FAILED), true);

        return is_array($stored) ? $stored : [];
    }
}
