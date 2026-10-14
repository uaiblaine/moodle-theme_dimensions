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
 * Theme Dimensions - The public category page on Moodle's router
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\route\controller;

use core\param;
use core\router\route;
use core\router\schema\parameters\path_parameter;
use theme_dimensions\local\category\request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The public page of a category at /theme_dimensions/category/{category}.
 *
 * The prefix is core's: a plugin's page route always carries its frankenstyle
 * name (\core\router\util::normalise_component_path()), and only core may place
 * routes anywhere. Three things about the route's shape are decisions:
 *
 * - No requirelogin. The page serves visitors of a public category while the
 *   site forces login, and decides for itself who sees what.
 * - A plain integer parameter, never a category path type. A resolver of that
 *   kind looks the category up and throws not_found before the controller runs:
 *   a 404 for a missing category against a redirect for a real one is an
 *   existence oracle for an anonymous client, which is the one thing this page
 *   is arranged to deny. The request class keeps its one refusal for every
 *   visitor.
 * - GET only. Every control of the page - the toolbar, the pager, the hero's
 *   fold - is a GET form or a link, so nothing posts here and accepting a POST
 *   would only widen the surface.
 *
 * Every decision is in {@see request::handle()}; this method only turns its
 * answer into a response, the way core's restricted_module controller does.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category {
    use \core\router\route_controller;

    /**
     * Answer a request for a category's public page.
     *
     * @param ServerRequestInterface $request The request.
     * @param ResponseInterface $response The response to fill.
     * @param int $category The category id from the path.
     * @return ResponseInterface The page, or a redirect.
     */
    #[route(
        path: '/category/{category}',
        method: ['GET'],
        pathtypes: [
            new path_parameter(name: 'category', type: param::INT),
        ],
    )]
    public function view(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $category,
    ): ResponseInterface {
        global $OUTPUT;

        $answer = request::handle($category);
        if ($answer->redirect !== null) {
            return $this->redirect($response, $answer->redirect);
        }

        // Guarded like core's restricted_module controller: under PHPUnit the header renders nothing.
        if ($header = $OUTPUT->header()) {
            $response->getBody()->write($header);
        }
        if ($skiplink = $OUTPUT->skip_link_target()) {
            $response->getBody()->write($skiplink);
        }
        $response->getBody()->write($OUTPUT->render($answer->page));
        if ($footer = $OUTPUT->footer()) {
            $response->getBody()->write($footer);
        }
        return $response;
    }
}
