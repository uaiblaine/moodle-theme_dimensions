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
 * Theme Dimensions - Public page of a category, the script entry
 *
 * The page's canonical address while the site's router is not configured, and
 * a redirect to the route the rest of the time; every decision is in
 * local\category\request, shared with the route controller. The redirect is
 * issued before the category is even looked up, so it reveals nothing about
 * the id, and it carries the request's own filters and page so that a link
 * somebody shared keeps landing on what it showed. It is a 303 through
 * redirect(), not a 301: a browser caches a permanent redirect, and a site
 * whose router is later switched off would keep sending cached visitors to a
 * route nothing answers. Scrapers learn the canonical from the page itself.
 *
 * No require_login(): this page exists to serve visitors who are not logged in
 * at all, and it decides for itself which categories they may see. A viewer
 * who IS logged in is sent to the showcase, which is the surface their rights
 * are read on.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../config.php');

use theme_dimensions\local\category\request;
use theme_dimensions\local\hotsite\links;

$id = required_param('id', PARAM_INT);

if (links::routing_enabled()) {
    redirect(links::category_page($id, request::forwarded_params($id)));
}

$answer = request::handle($id);
if ($answer->redirect !== null) {
    redirect($answer->redirect);
}

echo $OUTPUT->header();
echo $OUTPUT->skip_link_target();
echo $OUTPUT->render($answer->page);
echo $OUTPUT->footer();
