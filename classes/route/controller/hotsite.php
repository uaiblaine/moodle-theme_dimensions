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
 * Theme Dimensions - The hotsite on Moodle's router
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\route\controller;

use core\param;
use core\router\route;
use core\router\schema\parameters\path_parameter;
use core\router\schema\parameters\query_parameter;
use theme_dimensions\local\hotsite\links;
use theme_dimensions\local\hotsite\request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The hotsite at /theme_dimensions/hotsite/{course}.
 *
 * The prefix is core's: a plugin's page route always carries its frankenstyle
 * name (\core\router\util::normalise_component_path()), and only core may place
 * routes anywhere. Three things about the route's shape are decisions:
 *
 * - No requirelogin. The page serves visitors of a public course while the
 *   site forces login, and decides for itself who sees what.
 * - A plain integer parameter, never path_course. That one resolves an id, an
 *   idnumber: and a name:<shortname> and throws not_found before the controller
 *   runs: a 404 for a missing course against a redirect for a real one is an
 *   existence oracle for an anonymous client, and the shortname form is the
 *   enumeration surface after_config closes on course/info.php. The request
 *   class keeps its one refusal for every visitor.
 * - GET and POST. The enrol_self, enrol_fee and enrol_apply widgets and the
 *   page's own no-JS transports post to the page's address, which under the
 *   router is this route; so does the guest entry's interstitial form.
 *
 * The one query parameter the route declares is guest, the guest entry's ask
 * ({@see links::guest_page()}): core's validator strips every undeclared
 * parameter from the request the controller is handed. It is a raw string and
 * only '1' asks, so a stray value is the plain hotsite and never a validation
 * error - an integer type would answer "guest=yes" with a 500.
 *
 * Every decision is in {@see request::handle()}; this method only turns its
 * answer into a response, the way core's restricted_module controller does. A
 * redirect() raised inside an enrol plugin's page hook (enrol_self after a
 * successful enrolment) ends the request the old way, headers and exit, which
 * core's own util::redirect_with_params() does too.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hotsite {
    use \core\router\route_controller;

    /**
     * Answer a request for a course's hotsite.
     *
     * @param ServerRequestInterface $request The request.
     * @param ResponseInterface $response The response to fill.
     * @param int $course The course id from the path.
     * @return ResponseInterface The page (the hotsite or the guest entry's interstitial), or a redirect.
     */
    #[route(
        path: '/hotsite/{course}',
        method: ['GET', 'POST'],
        queryparams: [
            new query_parameter(name: links::GUEST_PARAM, type: param::RAW, default: ''),
        ],
        pathtypes: [
            new path_parameter(name: 'course', type: param::INT),
        ],
    )]
    public function view(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $course,
    ): ResponseInterface {
        global $OUTPUT;

        $guest = (string) ($request->getQueryParams()[links::GUEST_PARAM] ?? '') === '1';
        $answer = request::handle($course, $guest);
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
