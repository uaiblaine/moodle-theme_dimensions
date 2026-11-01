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
 * Theme Dimensions - The calendar page on Moodle's router
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\route\controller;

use core\router\route;
use theme_dimensions\local\calendar\page;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The calendar page at /theme_dimensions/calendar.
 *
 * The prefix is core's: a plugin's page route always carries its frankenstyle name. Two things about the route's
 * shape are decisions:
 *
 * - No requirelogin in the attribute. The page sends a visitor to the login page and the guest account to core's
 *   calendar, which a router-level login gate would answer with core's login redirect for both; the real account's
 *   require_login() runs inside {@see page::handle()}.
 * - GET only, and no declared query parameter. Every control of the page is a GET form or a link, and the state is
 *   read from the query string by optional_param() (which core's validator leaves alone), so a stray parameter is
 *   dropped by the state's own vocabulary rather than refused by the router.
 *
 * Every decision is in {@see page::handle()}; this method only turns its answer into a response, the way core's
 * restricted_module controller does.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calendar {
    use \core\router\route_controller;

    /**
     * Answer a request for the calendar page.
     *
     * @param ServerRequestInterface $request The request.
     * @param ResponseInterface $response The response to fill.
     * @return ResponseInterface The page, or a redirect.
     */
    #[route(
        path: '/calendar',
        method: ['GET'],
    )]
    public function view(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        global $OUTPUT;

        $answer = page::handle();
        if ($answer->redirect !== null) {
            return $this->redirect($response, $answer->redirect);
        }

        // Guarded like core's restricted_module controller: under PHPUnit the header renders nothing.
        if ($header = $OUTPUT->header()) {
            $response->getBody()->write($header);
        }
        $response->getBody()->write($OUTPUT->render($answer->page));
        if ($footer = $OUTPUT->footer()) {
            $response->getBody()->write($footer);
        }
        return $response;
    }
}
