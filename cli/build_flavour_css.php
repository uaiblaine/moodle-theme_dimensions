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
 * Theme Dimensions - Compile Boost Union flavour stylesheets ahead of their first visitor.
 *
 * With --flavourid, builds ONE sheet in this process and exits with the builder's answer (0 built, 2 skipped,
 * 3 present, 4 busy, 5 stale; 1 on a failure). That is the child the planner runs, one per flavour, because Boost
 * Union cannot compile two flavours in one process. With --all, runs the planner: every missing sheet, each in a
 * child of its own; a deploy step runs it after upgrade.php and purge_caches.php.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['flavourid' => 0, 'direction' => 'ltr', 'all' => false, 'dry-run' => false, 'help' => false],
    ['h' => 'help']
);
if ($unrecognised) {
    cli_error('Unknown option: ' . implode(', ', $unrecognised));
}

$help = "Compile Boost Union flavour stylesheets of theme_dimensions ahead of their first visitor.

Options:
  --all               Build every missing sheet, each flavour in a process of its own.
  --dry-run           With --all: list what would be built, and build nothing.
  --flavourid=ID      Build one flavour's sheet in this process (the planner's child).
  --direction=ltr     With --flavourid: ltr (default) or rtl.
  -h, --help          Print this help.

Exit status with --flavourid: 0 built, 2 nothing to build here, 3 already built, 4 the theme's lock was busy,
5 a theme revision moved during the compile, 6 this machine's files written from the shared cache (no compile),
1 failed. With --all: 0, or 1 when a sheet failed.

Example:
  php theme/dimensions/cli/build_flavour_css.php --all
";

if ($options['help'] || (!$options['all'] && empty($options['flavourid']))) {
    cli_writeln($help);
    exit(0);
}

if ($options['all']) {
    $results = (new \theme_dimensions\local\flavourcss\planner())->run((bool) $options['dry-run']);
    if (!$results) {
        cli_writeln('Every flavour sheet is built.');
    }
    foreach ($results as $code) {
        if ($code !== null && !in_array($code, \theme_dimensions\local\flavourcss\planner::ANSWERS, true)) {
            exit(1);
        }
    }
    exit(0);
}

try {
    $code = (new \theme_dimensions\local\flavourcss\builder())->build((int) $options['flavourid'], (string) $options['direction']);
} catch (\Throwable $e) {
    cli_problem(get_class($e) . ': ' . $e->getMessage());
    exit(1);
}
exit($code);
