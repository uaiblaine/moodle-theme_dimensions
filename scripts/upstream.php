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
 * Theme Dimensions - Write today's upstream of every copy into a directory, for scripts/upstream-merge.sh.
 *
 * A development tool, never shipped (/scripts is export-ignored). It runs on a Moodle site where this checkout
 * is installed as theme/dimensions, because today's upstream is what that site's template finder and class loader
 * resolve ({@see \theme_dimensions\local\upstream}). It writes into the export directory only, never into the theme.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(['export' => '', 'help' => false], ['h' => 'help']);

if ($unrecognised || $options['help'] || $options['export'] === '') {
    cli_writeln('Write today\'s upstream of every theme_dimensions copy under a directory, one file per snapshot.

Options:
--export=DIR   The directory to write into (created if missing).
-h, --help     Print this help.

Prints one line per copy: snapshot path, copy path and upstream source, separated by tabs.');
    exit($options['help'] ? 0 : 1);
}

$export = rtrim($options['export'], '/');
foreach (\theme_dimensions\local\upstream::entries() as $entry) {
    $target = $export . '/' . $entry['snapshot'];
    check_dir_exists(dirname($target));
    file_put_contents($target, $entry['text']);
    cli_writeln($entry['snapshot'] . "\t" . $entry['copy'] . "\t" . $entry['source']);
}
