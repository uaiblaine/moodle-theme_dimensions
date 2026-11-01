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
 * Theme Dimensions - The calendar page, the script entry
 *
 * The page's canonical address while the site's router is not configured, and a 303 to the route the rest of the
 * time, carrying the request's month and state; every decision is in local\calendar\page, shared with the route
 * controller. A 303 through redirect(), never a 301: a browser caches a permanent redirect, and a site whose router
 * is later switched off would keep sending cached readers to a route nothing answers.
 *
 * No require_login() here: the page sends a visitor to the login page and the guest account to core's calendar
 * first, and calls require_login() itself for a real account (local\calendar\page::handle()).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../config.php');

use theme_dimensions\local\calendar\page;
use theme_dimensions\local\landing\links;

if (links::routing_enabled()) {
    redirect(page::url(page::forwarded_params()));
}

$answer = page::handle();
if ($answer->redirect !== null) {
    redirect($answer->redirect);
}

echo $OUTPUT->header();
echo $OUTPUT->render($answer->page);
echo $OUTPUT->footer();
