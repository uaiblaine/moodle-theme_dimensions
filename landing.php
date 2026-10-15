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
 * Theme Dimensions - Course enrolment landing page, the script entry
 *
 * The page's canonical address while the site's router is not configured, and
 * a redirect to the route the rest of the time; every decision is in
 * local\landing\request, shared with the route controller. The redirect is
 * issued before the course is even looked up, so it reveals nothing about the
 * id, and old links and cached previews keep resolving. It is a 303 through
 * redirect(), not a 301: a browser caches a permanent redirect, and a site
 * whose router is later switched off would keep sending cached visitors to a
 * route nothing answers. Scrapers learn the canonical from the page itself.
 *
 * No require_login(): that call is what sends a non-enrolled user to the
 * standard enrolment page, and this page serves non-enrolled users and, for a
 * public course, visitors who are not logged in at all.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../config.php');

use theme_dimensions\local\landing\links;
use theme_dimensions\local\landing\request;

$courseid = required_param('id', PARAM_INT);
// The guest entry's ask; only 1 asks, as on the route. A POST of the entry's form never reaches
// the redirect below: the form posts to links::page(), which is the route while the router is on.
$guest = optional_param(links::GUEST_PARAM, 0, PARAM_INT) === 1;

if (links::routing_enabled()) {
    redirect($guest ? links::guest_page($courseid) : links::page($courseid));
}

$answer = request::handle($courseid, $guest);
if ($answer->redirect !== null) {
    redirect($answer->redirect);
}

echo $OUTPUT->header();
echo $OUTPUT->render($answer->page);
echo $OUTPUT->footer();
