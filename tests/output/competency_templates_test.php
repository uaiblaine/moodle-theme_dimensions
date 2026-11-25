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
 * Theme Dimensions - Tests for the theme's copies of the competency framework's templates
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Pins the hooks core's JavaScript and Behat read in the theme's copies of tool_lp, report_competency
 * and block_lp templates.
 *
 * The theme restyles those templates and must never change what they do. The hooks below are read by
 * one of core's AMD modules (tool_lp/evidence_delete, tool_lp/competencyactions,
 * tool_lp/competencydialogue, tool_lp/grade_dialogue, tool_lp/user_competency_workflow,
 * tool_lp/user_competency_info, the three navigation modules, report_competency/grading_popup,
 * tool_lp/planactions and tool_lp/user_evidence_actions with tool_lp/menubar), by a
 * core Behat selector (tool_lp's and report_competency's named selectors), or are a literal core's own
 * template hands to one of those modules; the few no reader reads (class hooks core styles, the
 * statistics, summary and grader regions, block_lp's page regions) are kept to mirror core's markup.
 * A copy that drops one
 * renders, looks right and silently breaks a rating, a deletion or a dialogue, so two independent
 * checks hold each one: the hook's literal is in core's own file and in the override, and the
 * rendered override carries it as an element.
 *
 * Each template is rendered through the real engine under this theme, from its own example context -
 * the one context the mustache lint also validates - so what is parsed is what a browser receives,
 * and a dim-comp class in the result proves the theme's copy answered rather than core's.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class competency_templates_test extends \advanced_testcase {
    /** @var string The core release every copy mirrors, as each docblock names it. */
    private const MIRRORS = 'Moodle 5.3 (2026100500)';

    /** @var bool Whether this test has put the global page under the theme. */
    private bool $themed = false;

    /**
     * Every override, by name, with core's file relative to the public directory.
     *
     * @return array Template name => core's file.
     */
    private static function overrides(): array {
        return [
            'tool_lp/progress_bar' => 'admin/tool/lp/templates/progress_bar.mustache',
            'tool_lp/competency_summary' => 'admin/tool/lp/templates/competency_summary.mustache',
            'tool_lp/competency_path' => 'admin/tool/lp/templates/competency_path.mustache',
            'tool_lp/evidence_summary' => 'admin/tool/lp/templates/evidence_summary.mustache',
            'tool_lp/user_summary' => 'admin/tool/lp/templates/user_summary.mustache',
            'tool_lp/linked_courses_summary' => 'admin/tool/lp/templates/linked_courses_summary.mustache',
            'tool_lp/module_navigation' => 'admin/tool/lp/templates/module_navigation.mustache',
            'tool_lp/competency_plan_navigation' => 'admin/tool/lp/templates/competency_plan_navigation.mustache',
            'tool_lp/user_competency_course_navigation' =>
                'admin/tool/lp/templates/user_competency_course_navigation.mustache',
            'tool_lp/related_competencies' => 'admin/tool/lp/templates/related_competencies.mustache',
            'tool_lp/course_competency_statistics' => 'admin/tool/lp/templates/course_competency_statistics.mustache',
            'tool_lp/template_statistics' => 'admin/tool/lp/templates/template_statistics.mustache',
            'tool_lp/competency_grader' => 'admin/tool/lp/templates/competency_grader.mustache',
            'tool_lp/user_competency_summary' => 'admin/tool/lp/templates/user_competency_summary.mustache',
            'tool_lp/plans_page' => 'admin/tool/lp/templates/plans_page.mustache',
            'tool_lp/course_competencies_page' => 'admin/tool/lp/templates/course_competencies_page.mustache',
            'tool_lp/user_competency_summary_in_course' =>
                'admin/tool/lp/templates/user_competency_summary_in_course.mustache',
            'tool_lp/user_evidence_list_page' => 'admin/tool/lp/templates/user_evidence_list_page.mustache',
            'report_competency/report' => 'report/competency/templates/report.mustache',
            'report_competency/user_competency_summary' => 'report/competency/templates/user_competency_summary.mustache',
            'report_competency/user_course_navigation' => 'report/competency/templates/user_course_navigation.mustache',
            'block_lp/summary' => 'blocks/lp/templates/summary.mustache',
            'block_lp/competencies_to_review_page' => 'blocks/lp/templates/competencies_to_review_page.mustache',
            'block_lp/plans_to_review_page' => 'blocks/lp/templates/plans_to_review_page.mustache',
        ];
    }

    /**
     * The hooks of each override: the literal core's file and the copy must both contain, and the
     * XPath expressions the copy's rendered example must match.
     *
     * The literals are the attribute spellings core's modules and Behat selectors look for. The
     * expressions match the rendered element, with the example context's values, so a hook that is
     * spelt in the file but no longer reaches the page (a moved section, a lost loop) reddens too.
     * Every list carries one expression on a dim-comp class: the proof the theme's copy rendered.
     *
     * @return array Template name => [[literal, ...], [xpath, ...]].
     */
    private static function hooks(): array {
        return [
            'tool_lp/progress_bar' => [
                ['<progress', 'max="100"', '{{$progresstext}}', '{{$percentage}}'],
                [
                    "//label[contains(@class, 'dim-comp-progress')]//progress[@max='100'][@value='75']",
                    "//label[contains(@class, 'dim-comp-progress')][contains(., '3 out of 4')]",
                ],
            ],
            'tool_lp/competency_summary' => [
                ['id="competency_link_{{competency.id}}"', 'competency-heading', '{{> tool_lp/competency_path }}',
                    '{{> tool_lp/related_competencies }}'],
                [
                    "//*[contains(@class, 'dim-comp-summary')]//h4[@id='competency_link_1']",
                    "//*[contains(@class, 'competency-heading')]",
                    "//nav[starts-with(@id, 'competency-path-')]",
                    "//*[@data-region='relatedcompetencies']//a[@data-action='competency-dialogue'][@data-id='3']",
                ],
            ],
            'tool_lp/competency_path' => [
                ['id="competency-path-{{uniqid}}"', 'competencyframeworkid={{framework.id}}', 'competencyid={{id}}',
                    'tool_lp/competencydialogue'],
                [
                    "//nav[starts-with(@id, 'competency-path-')][contains(@class, 'dim-comp-path')]",
                    "//nav//a[contains(@href, 'competencyframeworkid=1')]",
                    "//nav//li[@aria-current='location'][contains(., 'Communicate clearly')]",
                ],
            ],
            'tool_lp/evidence_summary' => [
                ['data-region="evidence"', 'data-id="{{id}}"', 'data-action="delete-evidence"', 'tool_lp/evidence_delete'],
                [
                    "//div[@data-region='evidence'][@data-id='1'][contains(@class, 'dim-comp-evidence')]",
                    "//*[@data-region='evidence']//a[@data-action='delete-evidence']",
                    "//*[@data-region='evidence']//span[contains(concat(' ', @class, ' '), ' badge ')][contains(., 'Met')]",
                ],
            ],
            'tool_lp/user_summary' => [
                ['{{profileurl}}', '{{profileimageurlsmall}}', '{{fullname}}'],
                [
                    "//a[contains(@class, 'dim-comp-user')][@href='https://example.com/user/profile.php?id=2']//img[@alt='']",
                    "//a[contains(@class, 'dim-comp-user')][contains(., 'Tess Teacher')]",
                ],
            ],
            'tool_lp/linked_courses_summary' => [
                ['{{viewurl}}', 'coursesusingthiscompetency', 'nolinkedcourses'],
                [
                    "//*[contains(@class, 'dim-comp-courses')]//ol/li/a[@href='https://example.com/course/view.php?id=2']",
                ],
            ],
            'tool_lp/module_navigation' => [
                ['user-competency-course-navigation', 'id="module-nav-{{uniqid}}"', 'name="courseid"', 'name="mod"',
                    'tool_lp/module_navigation'],
                [
                    "//div[contains(@class, 'dim-comp-nav')]//form[contains(@class, 'user-competency-course-navigation')]",
                    "//form//select[starts-with(@id, 'module-nav-')]/option[@value='5']",
                    "//form//label[starts-with(@for, 'module-nav-')]",
                    "//form//input[@type='hidden'][@name='courseid'][@value='2']",
                    "//form//input[@type='hidden'][@name='mod'][@value='0']",
                ],
            ],
            'tool_lp/competency_plan_navigation' => [
                ['id="competency-nav-{{uniqid}}"', 'tool_lp/competency_plan_navigation'],
                [
                    "//div[contains(@class, 'dim-comp-nav')]//select[starts-with(@id, 'competency-nav-')]" .
                        "[contains(@class, 'form-select')]/option[@value='3']",
                    "//label[starts-with(@for, 'competency-nav-')]",
                ],
            ],
            'tool_lp/user_competency_course_navigation' => [
                ['user-competency-course-navigation', 'id="user-nav-{{uniqid}}"', 'id="competency-nav-{{uniqid}}"',
                    'name="userid"', 'name="competencyid"', 'name="courseid"', 'tool_lp/user_competency_course_navigation'],
                [
                    "//div[contains(@class, 'dim-comp-nav')]//form[contains(@class, 'user-competency-course-navigation')]",
                    "//form//select[starts-with(@id, 'user-nav-')]/option[@value='4']",
                    "//form//select[starts-with(@id, 'competency-nav-')]/option[@value='1']",
                    "//form//input[@type='hidden'][@name='userid'][@value='3']",
                    "//form//input[@type='hidden'][@name='competencyid'][@value='1']",
                    "//form//input[@type='hidden'][@name='courseid'][@value='2']",
                ],
            ],
            'tool_lp/related_competencies' => [
                ['data-region="relatedcompetencies"', 'data-action="deleterelation"', 'id="id-related-{{id}}"',
                    'data-action="competency-dialogue"', 'data-id="{{id}}"'],
                [
                    "//div[@data-region='relatedcompetencies'][contains(@class, 'dim-comp-related')]",
                    "//*[@data-region='relatedcompetencies']//a[@data-action='deleterelation'][@id='id-related-3']",
                    "//*[@data-region='relatedcompetencies']//a[@data-action='competency-dialogue'][@data-id='3']" .
                        "[normalize-space(.)='Listen actively COMM-2']",
                ],
            ],
            'tool_lp/course_competency_statistics' => [
                ['data-region="coursecompetencystatistics"', '{{< tool_lp/progress_bar}}', '#competency-info-link-{{id}}'],
                [
                    "//div[@data-region='coursecompetencystatistics'][contains(@class, 'dim-comp-stats')]//progress[@value='75']",
                    "//*[@data-region='coursecompetencystatistics']//a[@href='#competency-info-link-1']",
                ],
            ],
            'tool_lp/template_statistics' => [
                ['data-region="templatestatistics"', '{{< tool_lp/progress_bar}}', '#competency_link_{{id}}'],
                [
                    "//div[@data-region='templatestatistics'][contains(@class, 'dim-comp-stats')]",
                    "//*[@data-region='templatestatistics']//progress[@value='50']",
                    "//*[@data-region='templatestatistics']//progress[@value='75']",
                    "//*[@data-region='templatestatistics']//progress[@value='60']",
                    "//*[@data-region='templatestatistics']//a[@href='#competency_link_1']",
                ],
            ],
            'tool_lp/competency_grader' => [
                ['competency-grader', 'data-region="competency-grader"', 'data-action="rate"', 'data-action="cancel"',
                    'name="rating"', 'name="comment"', 'data-region="rating"', 'data-region="comment"', 'data-region="footer"'],
                [
                    "//div[@data-region='competency-grader'][contains(@class, 'competency-grader')]" .
                        "[contains(@class, 'dim-comp-grader')]//form",
                    "//*[@data-region='competency-grader']//*[@data-region='rating']//select[@name='rating']" .
                        "[starts-with(@id, 'rating_')]/option[@value='2'][@selected]",
                    "//*[@data-region='competency-grader']//*[@data-region='comment']//textarea[@name='comment']" .
                        "[starts-with(@id, 'comment_')]",
                    "//*[@data-region='competency-grader']//*[@data-region='footer']//button[@data-action='rate']",
                    "//*[@data-region='competency-grader']//*[@data-region='footer']//button[@data-action='cancel']",
                    "//label[starts-with(@for, 'rating_')]",
                    "//label[starts-with(@for, 'comment_')]",
                ],
            ],
            'tool_lp/user_competency_summary' => [
                ['data-region="user-competency-full-info"', 'data-node="user-competency"', 'data-region-id="{{uniqid}}"',
                    'data-region="competency-summary"', 'data-region="user-competency-status"', 'data-action="request-review"',
                    'data-action="cancel-review-request"', 'data-action="start-review"', 'data-action="stop-review"',
                    'id="rate_{{uniqid}}"', 'data-region="evidence-listing"', 'tool_lp/grade_user_competency_inline',
                    'tool_lp/user_competency_workflow', '{{> tool_lp/evidence_summary }}'],
                [
                    "//div[@data-region='user-competency-full-info'][@data-node='user-competency']" .
                        "[@data-competencyid='1'][@data-userid='3'][@data-region-id][contains(@class, 'dim-comp-ucsummary')]",
                    "//*[@data-region='competency-summary']//*[@data-region='user-competency-status']" .
                        "//button[@data-action='cancel-review-request']",
                    "//*[@data-region='user-competency-status']//button[@data-action='start-review']",
                    "//button[starts-with(@id, 'rate_')]",
                    "//dt[contains(., 'Proficient')]/following-sibling::dd[1][contains(., 'Yes')]",
                    "//dt[contains(., 'Rating')]/following-sibling::dd[1][contains(., 'Competent')]",
                    "//dl[@data-region='evidence-listing']//*[@data-region='evidence'][@data-id='1']",
                ],
            ],
            'tool_lp/plans_page' => [
                ['data-region="plans"', 'data-region="plan-node"', 'data-id="{{id}}"', 'data-userid="{{userid}}"',
                    'class="planactions"', 'class="dropdown dropdown-menu"', 'class="dropdown-item"',
                    'data-action="plan-reopen"', 'data-action="plan-complete"', 'data-action="plan-request-review"',
                    'data-action="plan-cancel-review-request"', 'data-action="plan-start-review"',
                    'data-action="plan-stop-review"', 'data-action="plan-approve"', 'data-action="plan-unapprove"',
                    'data-action="plan-unlink"', 'data-action="plan-delete"', '{{#canmanage}}', '{{#canbeedited}}',
                    '{{pluginbaseurl}}/editplan.php?id={{id}}&amp;userid={{userid}}&amp;return=plans',
                    '{{pluginbaseurl}}/plan.php?id={{id}}', '{{{name}}}', '{{#navigation}}', '{{{.}}}',
                    '{{#canreaduserevidence}}', '{{pluginbaseurl}}/user_evidence_list.php?userid={{userid}}',
                    'nouserplans, tool_lp', "'tool_lp/planactions'", "new ActionsMod('plans')",
                    "enhanceMenubar('.planactions')"],
                [
                    "//div[@data-region='plans'][contains(@class, 'dim-comp-plans')][@data-dim-layout='horizontal']",
                    "//*[@data-region='plans']//ul[contains(concat(' ', @class, ' '), ' block_dimensions ')]" .
                        "/li[@data-region='plan-node'][@data-id='4'][@data-userid='3'][@data-dim-status='active']",
                    "//li[@data-region='plan-node'][@data-id='5'][@data-dim-status='draft']",
                    "//li[@data-id='4']//*[@data-region='dim-comp-extra'][@data-planid='4']" .
                        "//article[contains(@class, 'plan-card')]" .
                        "//a[@href='https://example.com/local/dimensions/view-plan.php?id=4']",
                    "//li[@data-id='5']//article[contains(@class, 'dim-comp-plan-fallback')]" .
                        "//a[@href='https://example.com/admin/tool/lp/plan.php?id=5'][normalize-space(.)='Garden plan']",
                    "//li[@data-id='4']//ul[@class='planactions']/li/a[@href='#']/following-sibling::ul" .
                        "[@class='dropdown dropdown-menu']/li[@class='dropdown-item']/a[@data-action='plan-complete']",
                    "//li[@data-id='4']//a[@href='https://example.com/admin/tool/lp/editplan.php?id=4&userid=3&return=plans']",
                    "//li[@data-id='4']//a[@data-action='plan-unlink']",
                    "//li[@data-id='5']//a[@data-action='plan-request-review']",
                    "//li[@data-id='5']//a[@data-action='plan-approve']",
                    "//li[@data-id='5']//a[@data-action='plan-delete']",
                    "//*[@data-region='plans']//a[@href='https://example.com/admin/tool/lp/editplan.php?userid=3']",
                    "//a[@href='https://example.com/admin/tool/lp/user_evidence_list.php?userid=3']",
                    "//*[@data-region='dim-comp-bar'][@hidden]//*[@role='radiogroup']/button[@role='radio'][@data-value='draft']",
                    "//*[@data-region='dim-comp-total'][@aria-live='polite']",
                    "//*[@data-region='dim-comp-nomatch'][@hidden]",
                    // Phase 1c: the sort's dates from core's keys, the first paint's favourites and tags.
                    "//li[@data-id='4'][@data-dim-due='1798761600'][@data-dim-modified='1767225600']" .
                        "[@data-dim-fav='1'][@data-dim-tag1='2026']",
                    "//li[@data-id='5'][@data-dim-due='0'][@data-dim-fav='0'][not(@data-dim-tag1)]",
                    "//div[@data-region='plans'][@data-dim-favourites='1']" .
                        "[@data-dim-starmodule='block_dimensions/favourite_star'][@data-dim-tagfilters]",
                    // The cluster: the count, then the capsule with the star, Filters and Organise, the star and
                    // Filters hidden until the module knows they apply.
                    "//*[@data-region='dim-comp-cluster']/span[@data-region='dim-comp-total']" .
                        "/following-sibling::div[@data-region='dim-comp-tools']" .
                        "[button[1][@data-action='dim-comp-fav'][@aria-pressed='false'][@hidden]]" .
                        "[button[2][@data-action='dim-comp-filters'][@aria-expanded='false'][@hidden]" .
                        "//*[@data-region='dim-comp-badge'][@aria-hidden='true'][@hidden]]" .
                        "[button[3][@data-action='dim-comp-organise'][@aria-expanded='false']]",
                    "//*[@data-region='dim-comp-organise-menu'][@role='menu'][@hidden]//*[@role='group']" .
                        "[button[@role='menuitemradio'][@data-value='name'][@aria-checked='true']]" .
                        "[button[@role='menuitemradio'][@data-value='due'][@aria-checked='false']]" .
                        "[button[@role='menuitemradio'][@data-value='modified'][@aria-checked='false']]",
                    "//*[@data-region='dim-comp-fpanel'][@hidden]//*[@data-action='dim-comp-clearfilters']" .
                        "/following-sibling::button[@data-action='dim-comp-show']",
                    "//*[@data-region='dim-comp-bar']//*[@data-region='dim-comp-chips'][@hidden]",
                ],
            ],
            'tool_lp/course_competencies_page' => [
                ['data-region="coursecompetenciespage"', 'data-region="actions"', '{{#canmanagecoursecompetencies}}',
                    'addcoursecompetencies, tool_lp', 'data-region="configurecoursecompetencies"',
                    '{{#cangradecompetencies}}', 'coursecompetencyratingsarepushedtouserplans, tool_lp',
                    'coursecompetencyratingsarenotpushedtouserplans, tool_lp', '{{#canconfigurecoursecompetencies}}',
                    'data-action="configure-course-competency-settings"', 'data-courseid="{{courseid}}"',
                    'data-pushratingstouserplans="{{settings.pushratingstouserplans}}"',
                    '{{> tool_lp/course_competency_statistics }}', 'data-region="coursecompetencies"', 'managecompetencies',
                    'class="drag-parentnode"', 'class="drag-samenode" data-id="{{competency.id}}"',
                    'class="drag-handlecontainer float-sm-start"',
                    'data-action="delete-competency-link" data-id="{{competency.id}}"',
                    'user_competency_in_course.php?courseid={{courseid}}', 'id="competency-info-link-{{competency.id}}"',
                    'title="{{#str}}viewdetails, tool_lp{{/str}}"', '{{{competency.shortname}}}', '{{competency.idnumber}}',
                    '{{{competency.description}}}', '{{> tool_lp/competency_path }}', '{{#usercompetencycourse}}',
                    '{{gradename}}', 'data-region="coursecompetency-ruleoutcome"', 'uponcoursecompletion, tool_lp',
                    'data-field="ruleoutcome" data-id="{{coursecompetency.id}}"',
                    '<option value="{{value}}" {{#selected}}selected{{/selected}}>{{text}}</option>',
                    'data-region="coursecompetencyactivities"', '<a href="{{url}}">', 'noactivities, tool_lp',
                    'data-region="learningplans"', '{{pluginbaseurl}}/plan.php?id={{id}}', '{{{name}}}',
                    'nouserplanswithcompetency, core_competency', 'nocompetenciesinactivity, tool_lp',
                    'nocompetenciesincourse, tool_lp', 'managecompetenciesandframeworks, tool_lp', "'tool_lp/competencies'",
                    "'course', {{pagecontextid}}", "'tool_lp/course_competency_settings'",
                    "'[data-action=configure-course-competency-settings]'"],
                [
                    "//div[@data-region='coursecompetenciespage'][contains(@class, 'dim-comp-course')][@data-dim-extras='1']",
                    "//div[@data-region='actions']//button[@disabled][contains(., 'Add competencies to course')]",
                    "//*[@data-region='configurecoursecompetencies']//a[@data-action='configure-course-competency-settings']" .
                        "[@data-courseid='2'][@data-pushratingstouserplans='1']",
                    "//*[@data-region='coursecompetencies']//table[contains(@class, 'managecompetencies')]" .
                        "/tbody[contains(@class, 'drag-parentnode')]/tr[contains(@class, 'drag-samenode')][@data-id='7']",
                    "//tr[@data-id='7']//span[contains(@class, 'drag-handlecontainer')]",
                    "//tr[@data-id='7']//a[@data-action='delete-competency-link'][@data-id='7']",
                    "//tr[@data-id='7']//a[@title='View details'][@id='competency-info-link-7']" .
                        "[contains(., 'Assessment strategies')]",
                    // Tool_lp's named selector "competency description" reads a p directly in the cell.
                    "//tr[@data-id='7']/td/p[contains(., 'formative and summative')]",
                    "//tr[@data-id='7']//span[contains(concat(' ', @class, ' '), ' badge ')][contains(., 'Competent')]",
                    "//*[@data-region='coursecompetency-ruleoutcome']/label/select[@data-field='ruleoutcome'][@data-id='1']" .
                        "/option[@value='1'][@selected]",
                    "//*[@data-region='coursecompetencyactivities']//a[@href='https://example.com/mod/assign/view.php?id=31']",
                    "//*[@data-region='dim-comp-cm'][@data-cmid='31']//span[contains(@class, 'dim-comp-pill--rated')]",
                    "//*[@data-region='learningplans']//a[@href='https://example.com/admin/tool/lp/plan.php?id=4']",
                    "//tr[@data-id='7']//*[@data-region='dim-comp-extra'][@data-competencyid='7']//progress[@value='58']",
                ],
            ],
            'tool_lp/user_competency_summary_in_course' => [
                ['data-region="user-competency-full-info"', 'data-node="user-competency"',
                    'data-competencyid="{{usercompetency.competencyid}}"', 'data-userid="{{usercompetency.userid}}"',
                    'data-region-id="{{uniqid}}"', 'data-region="competency-summary"', '{{> tool_lp/competency_summary }}',
                    '{{#cangrade}}', 'id="rate_{{uniqid}}"', 'rate, tool_lp', 'data-region="coursecompetencyactivities"',
                    'noactivities, tool_lp', '{{pluginbaseurl}}/plan.php?id={{id}}', '{{{name}}}',
                    'nouserplanswithcompetency, competency', 'proficient, tool_lp', 'rating, tool_lp', '{{proficiencyname}}',
                    '{{>tool_lp/comment_area}}', 'data-region="evidence-listing"', 'noevidence, tool_lp',
                    'tool_lp/grade_user_competency_inline', 'tool_lp/user_competency_info', "'{{competency.competency.id}}'",
                    "'{{user.id}}'", "'{{course.id}}'", "'{{competency.scaleid}}'", '{{#str}}chooserating, tool_lp{{/str}}',
                    "inlineGrader.on('competencyupdated', infoReloader.reload.bind(infoReloader));"],
                [
                    "//div[@data-region='user-competency-full-info'][@data-node='user-competency'][@data-region-id]" .
                        "[contains(@class, 'dim-comp-ucc')]/div[@data-region='competency-summary']",
                    "//*[@data-region='competency-summary']//*[contains(@class, 'dim-comp-summary')]" .
                        "//h4[@id='competency_link_1']",
                    "//*[contains(@class, 'dim-comp-ucc-primary')]/button[starts-with(@id, 'rate_')]" .
                        "[contains(@class, 'btn-primary')][normalize-space(.)='Rate']",
                    "//dt[contains(., 'Proficient')]/following-sibling::dd[1][contains(., 'Yes')]",
                    "//dt[contains(., 'Rating')]/following-sibling::dd[1][contains(., 'Competent')]",
                    "//*[@data-region='dim-comp-ucc-band']//dd[@data-dim-figure='evidence'][normalize-space(.)='2']",
                    "//*[@data-region='dim-comp-ucc-band']//dd[@data-dim-figure='plans'][normalize-space(.)='1']",
                    "//dd[@data-region='dim-comp-ucc-latest'][not(@hidden)]/span[@data-dim-figure='latest']" .
                        "[normalize-space(.)='Tuesday, 13 October 2026, 10:04']",
                    "//*[@data-region='evidence-listing']//table/tbody[@data-region='dim-comp-ucc-rows']" .
                        "/tr[@data-region='evidence'][@data-id='1']//a[@data-action='delete-evidence']",
                    "//tr[@data-region='evidence'][@data-id='1']/td[@data-dim-cell='when']" .
                        "[normalize-space(.)='Tuesday, 13 October 2026, 10:04']",
                    "//tr[@data-id='1']//span[contains(concat(' ', @class, ' '), ' badge ')][contains(., 'Competent')]",
                    "//tr[@data-id='1']//a[contains(@class, 'dim-comp-user')][contains(., 'Tess Teacher')]",
                    "//tr[@data-id='2']/th[@scope='row']//a[@href='https://example.com/mod/assign/view.php?id=7']",
                    "//*[@data-region='coursecompetencyactivities']/li/a[@href='https://example.com/mod/assign/view.php?id=7']",
                    "//li[@data-dim-plan='4']/a[@href='https://example.com/admin/tool/lp/plan.php?id=4']" .
                        "[normalize-space(.)='Writing plan']",
                    "//p[@data-region='dim-comp-ucc-empty'][@hidden]",
                ],
            ],
            'tool_lp/user_evidence_list_page' => [
                ['data-region="user-evidence-list"', "data-region='user-evidence-node'", 'data-id="{{id}}"',
                    'data-userid="{{userid}}"', 'class="user-evidence-actions"', 'class="dropdown dropdown-menu"',
                    'class="dropdown-item"', 'data-action="link-competency"', 'data-action="send-competencies-review"',
                    'data-action="user-evidence-delete"', '{{#canmanage}}', '{{#userhasplan}}',
                    '{{pluginbaseurl}}/user_evidence_edit.php?id={{id}}&amp;userid={{userid}}&amp;return=list',
                    '{{pluginbaseurl}}/user_evidence.php?id={{id}}', '{{{name}}}', '{{#navigation}}', '{{{.}}}',
                    '{{#usercompetencies}}', '{{usercompetency.statusname}}', '{{#files}}', '{{filenameshort}}', '{{urlshort}}',
                    'listofevidence, tool_lp', 'nouserevidence, tool_lp', "'tool_lp/user_evidence_actions'",
                    "new UserEvidenceActions('list')", "uea.enhanceMenubar('.user-evidence-actions')"],
                [
                    "//div[@data-region='user-evidence-list'][contains(@class, 'dim-comp-ev')]" .
                        "[@data-dim-firstpaint='1'][@data-dim-platform='1']",
                    "//*[@data-region='user-evidence-list']//ol[@data-region='dim-comp-ev-list']" .
                        "/li[@data-region='user-evidence-node'][@data-id='7'][@data-userid='3'][@data-dim-time='1792065600']" .
                        "[@data-dim-comps='12 ']",
                    "//ol[@data-region='dim-comp-ev-list']/li[@data-region='dim-comp-ev-month']" .
                        "/h3[normalize-space(.)='October 2026']",
                    "//li[@data-id='7']//h4/a[@href='https://example.com/admin/tool/lp/user_evidence.php?id=7']" .
                        "[normalize-space(.)='Data protection certificate']",
                    "//li[@data-id='7']//ul[@class='user-evidence-actions']/li/a[@href='#']/following-sibling::ul" .
                        "[@class='dropdown dropdown-menu']/li[@class='dropdown-item']/a[@data-action='user-evidence-delete']",
                    "//li[@data-id='7']//a[@data-action='link-competency']",
                    "//li[@data-id='8']//a[@data-action='send-competencies-review']",
                    "//li[@data-id='7']//a" .
                        "[@href='https://example.com/admin/tool/lp/user_evidence_edit.php?id=7&userid=3&return=list']",
                    "//li[@data-id='8']//*[contains(@class, 'dim-comp-pill--status')]",
                    "//*[@data-region='user-evidence-list']" .
                        "//a[@href='https://example.com/admin/tool/lp/user_evidence_edit.php?userid=3']",
                    "//*[@data-region='dim-comp-bar'][@hidden]//*[@role='radiogroup']" .
                        "/button[@role='radio'][@data-value='platform']",
                    "//*[@data-region='dim-comp-total'][@aria-live='polite']",
                    "//*[@data-region='dim-comp-ev-nomatch'][@hidden]",
                    "//aside[@data-region='dim-comp-ev-detail'][@hidden]//*[@data-region='dim-comp-ev-detailbody']",
                    "//button[@data-action='dim-comp-ev-more'][@hidden]",
                ],
            ],
            'report_competency/report' => [
                ['data-region="competency-breakdown-report"', 'data-courseid="{{course.id}}"', 'data-userid="{{user.id}}"',
                    'data-moduleid="{{moduleid}}"', 'data-region="configurecoursecompetencies"', 'data-user-competency="true"',
                    'data-competencyid="{{competencyid}}"', 'data-action="competency-dialogue"',
                    'report_competency/grading_popup', 'tool_lp/competencydialogue',
                    '{{> report_competency/user_competency_summary}}'],
                [
                    "//div[@data-region='competency-breakdown-report'][@data-courseid='2'][@data-userid='3']" .
                        "[@data-moduleid='0'][contains(@class, 'dim-comp-report')]",
                    "//*[@data-region='competency-breakdown-report']//*[@data-region='configurecoursecompetencies']",
                    "//*[@data-region='competency-breakdown-report']//table//tr/td[1]" .
                        "//a[@data-action='competency-dialogue'][@data-id='1']",
                    "//*[@data-region='competency-breakdown-report']//table//tr/td[2][@data-user-competency='true']" .
                        "[@data-userid='3'][@data-competencyid='1'][@data-courseid='2']" .
                        "//a[@title='User competency summary'][contains(., 'Competent')]",
                    "//table//tr/td[2]//a[@title='User competency summary'][normalize-space(.)='Not rated']",
                ],
            ],
            'report_competency/user_competency_summary' => [
                ['href="#"', 'usercompetencysummary, report_competency', 'notrated, report_competency', '{{gradename}}'],
                [
                    "//a[@href='#'][@title='User competency summary'][contains(@class, 'dim-comp-pills')]" .
                        "[contains(., 'Competent')]",
                ],
            ],
            'report_competency/user_course_navigation' => [
                ['user-competency-course-navigation', 'id="user-nav-{{uniqid}}"', 'id="module-nav-{{uniqid}}"',
                    'name="user"', 'name="id"', 'name="mod"', 'report_competency/user_course_navigation'],
                [
                    "//div[contains(@class, 'dim-comp-nav')]//form[contains(@class, 'user-competency-course-navigation')]",
                    "//form//select[starts-with(@id, 'user-nav-')]/option[@value='4']",
                    "//form//select[starts-with(@id, 'module-nav-')]/option[@value='5']",
                    "//form//label[starts-with(@for, 'module-nav-')]" .
                        "[normalize-space(.)='Filter competencies by resource or activity']",
                    "//form//input[@type='hidden'][@name='user'][@value='3']",
                    "//form//input[@type='hidden'][@name='id'][@value='2']",
                    "//form//input[@type='hidden'][@name='mod'][@value='0']",
                ],
            ],
            'block_lp/summary' => [
                ['{{plansurl}}', '{{usercompetency.url}}', '{{plan.url}}', 'competencies_to_review.php', 'plans_to_review.php'],
                [
                    "//div[contains(@class, 'dim-comp-block')]//a[@href='https://example.com/admin/tool/lp/plan.php?id=4']",
                    "//li[contains(@class, 'more')]/a[@href='https://example.com/admin/tool/lp/plans.php?userid=3']",
                    "//a[@href='https://example.com/admin/tool/lp/user_competency.php?id=7']",
                    "//a[@href='https://example.com/admin/tool/lp/plan.php?id=5']",
                ],
            ],
            'block_lp/competencies_to_review_page' => [
                ['data-region="competencies-to-review"', '{{usercompetency.url}}'],
                [
                    "//div[@data-region='competencies-to-review']//table[contains(@class, 'dim-comp-table')]/tbody/tr/td" .
                        "/a[@href='https://example.com/admin/tool/lp/user_competency.php?id=7']",
                ],
            ],
            'block_lp/plans_to_review_page' => [
                ['data-region="plans-to-review"', '{{plan.url}}'],
                [
                    "//div[@data-region='plans-to-review']//table[contains(@class, 'dim-comp-table')]/tbody/tr/td" .
                        "/a[@href='https://example.com/admin/tool/lp/plan.php?id=5']",
                ],
            ],
        ];
    }

    /**
     * One case per override, for the providers below.
     *
     * @return array Template name => [template name].
     */
    public static function override_provider(): array {
        $cases = [];
        foreach (array_keys(self::overrides()) as $name) {
            $cases[$name] = [$name];
        }
        return $cases;
    }

    /**
     * The path of the theme's copy of a template.
     *
     * @param string $name The template name, component first.
     * @return string The absolute path.
     */
    private function theme_file(string $name): string {
        [$component, $template] = explode('/', $name, 2);
        return dirname(__DIR__, 2) . '/templates/' . $component . '/' . $template . '.mustache';
    }

    /**
     * The example context of the theme's copy of a template, decoded.
     *
     * @param string $name The template name, component first.
     * @return array The context.
     */
    private function example_context(string $name): array {
        $source = file_get_contents($this->theme_file($name));
        $found = preg_match('/Example context \(json\):\s*(.*?)\n\}\}/s', $source, $matches);
        $this->assertSame(1, $found, "No example context in the theme's copy of {$name}.");
        $context = json_decode($matches[1], true);
        $this->assertIsArray($context, "The example context of {$name} is not valid JSON.");
        return $context;
    }

    /**
     * Render a template under this theme and parse the result.
     *
     * @param string $name The template name, component first.
     * @param array $context The context to render.
     * @return \DOMXPath
     */
    private function render_under_theme(string $name, array $context): \DOMXPath {
        global $PAGE;
        // The template finder reads the global page's theme, which may be forced once per page.
        if (!$this->themed) {
            $PAGE->set_context(\context_system::instance());
            $PAGE->set_url('/admin/tool/lp/plans.php');
            $PAGE->force_theme('dimensions');
            $this->themed = true;
        }
        $html = $PAGE->get_renderer('core')->render_from_template($name, $context);
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        return new \DOMXPath($document);
    }

    /**
     * A template's source without its Mustache comments.
     *
     * @param string $source The template source.
     * @return string The source, every comment removed.
     */
    private static function without_comments(string $source): string {
        return (string) preg_replace('/\{\{!.*?\}\}/s', '', $source);
    }

    /**
     * The normalised text of the first element an expression matches.
     *
     * @param \DOMXPath $xpath The parsed render.
     * @param string $expression The expression.
     * @return string The text, whitespace collapsed and trimmed.
     */
    private static function text_of(\DOMXPath $xpath, string $expression): string {
        $node = $xpath->query($expression)->item(0);
        return $node ? trim((string) preg_replace('/\s+/u', ' ', $node->textContent)) : '';
    }

    /**
     * Every template under templates/tool_lp, templates/report_competency and templates/block_lp is
     * listed above, with hooks.
     *
     * A new copy of a core template that nobody added to the hook table would be checked for nothing.
     *
     * @return void
     */
    public function test_every_competency_override_has_its_hooks_listed(): void {
        $found = [];
        foreach (['tool_lp', 'report_competency', 'block_lp'] as $component) {
            foreach (glob(dirname(__DIR__, 2) . '/templates/' . $component . '/*.mustache') as $file) {
                $found[] = $component . '/' . basename($file, '.mustache');
            }
        }
        sort($found);
        $listed = array_keys(self::overrides());
        sort($listed);

        $hooked = array_keys(self::hooks());
        sort($hooked);

        $this->assertNotEmpty($found);
        $this->assertSame($listed, $found);
        $this->assertSame($listed, $hooked, 'A template is listed without hooks, or hooks name no template.');
    }

    /**
     * Each copy names the core template and release it mirrors, and that file exists in this core.
     *
     * The docblock is the re-diff instruction for whoever upgrades core: it must name the file to
     * diff against, and the file must still be where it says.
     *
     * @param string $name The template name, component first.
     * @return void
     */
    #[DataProvider('override_provider')]
    public function test_each_copy_names_the_core_file_it_mirrors(string $name): void {
        global $CFG;
        $corefile = self::overrides()[$name];
        $source = file_get_contents($this->theme_file($name));

        $this->assertStringContainsString('@template ' . $name, $source);
        $this->assertStringContainsString(self::MIRRORS, $source);
        $flat = (string) preg_replace('/\s+/', '', $source);
        $this->assertStringContainsString($corefile, $flat, "The docblock of {$name} does not name {$corefile}.");
        $this->assertFileExists($CFG->dirroot . '/' . $corefile);
    }

    /**
     * Every hook literal is in core's template AND in the theme's copy.
     *
     * The first half proves the literal really is core's hook (a typo in this table would otherwise
     * pin a hook nobody reads); the second that the copy kept it. Both are read with their comments
     * removed, because each copy's docblock names the hooks it keeps.
     *
     * @param string $name The template name, component first.
     * @return void
     */
    #[DataProvider('override_provider')]
    public function test_every_core_hook_is_kept_in_the_copy(string $name): void {
        global $CFG;
        // Comments out: a docblock naming a hook is not the hook.
        $core = self::without_comments(file_get_contents($CFG->dirroot . '/' . self::overrides()[$name]));
        $copy = self::without_comments(file_get_contents($this->theme_file($name)));
        [$literals] = self::hooks()[$name];

        $this->assertNotEmpty($literals);
        foreach ($literals as $literal) {
            $this->assertStringContainsString($literal, $core, "{$literal} is not in core's {$name}: the table is wrong.");
            $this->assertStringContainsString($literal, $copy, "The theme's {$name} lost core's {$literal}.");
        }
    }

    /**
     * The theme's copy renders from its own example context, and the page gets every hook.
     *
     * @param string $name The template name, component first.
     * @return void
     */
    #[DataProvider('override_provider')]
    public function test_the_rendered_copy_carries_every_hook(string $name): void {
        $this->resetAfterTest();
        $xpath = $this->render_under_theme($name, $this->example_context($name));
        [, $expressions] = self::hooks()[$name];

        $this->assertNotEmpty($expressions);
        $themed = $xpath->query("//*[contains(@class, 'dim-comp')]")->length;
        $this->assertGreaterThan(0, $themed, "{$name} has no dim-comp class: core's template answered, not the theme's.");
        foreach ($expressions as $expression) {
            $this->assertGreaterThan(0, $xpath->query($expression)->length, "{$name} lost {$expression}.");
        }
    }

    /**
     * The evidence card is the one element carrying data-region="evidence".
     *
     * tool_lp/evidence_delete takes the id from the ancestors of the trigger with that region and
     * refuses when it finds more than one, so a wrapper of the theme's own wearing it would disarm
     * every deletion.
     *
     * @return void
     */
    public function test_the_evidence_region_is_not_nested(): void {
        $this->resetAfterTest();
        $xpath = $this->render_under_theme('tool_lp/evidence_summary', $this->example_context('tool_lp/evidence_summary'));

        $this->assertSame(1, $xpath->query("//*[@data-region='evidence']")->length);
        $this->assertSame(1, $xpath->query("//a[@data-action='delete-evidence']/ancestor::*[@data-region='evidence']")->length);
    }

    /**
     * The user competency summary's root is the one element carrying data-node="user-competency".
     *
     * tool_lp/user_competency_workflow reads the ids off exactly one such ancestor of a review button.
     *
     * @return void
     */
    public function test_the_user_competency_node_is_not_nested(): void {
        $this->resetAfterTest();
        $context = $this->example_context('tool_lp/user_competency_summary');
        $xpath = $this->render_under_theme('tool_lp/user_competency_summary', $context);

        $this->assertSame(1, $xpath->query("//*[@data-node='user-competency']")->length);
        $ancestors = "//button[@data-action='start-review']/ancestor::*[@data-node='user-competency']";
        $this->assertSame(1, $xpath->query($ancestors)->length);
    }

    /**
     * Three bars in one render name themselves by their own sentence, not by a shared id.
     *
     * The uniqid helper is one value per render, and core's template gave every bar of the template
     * statistics the same id, so a bar named through an id would read the first sentence three times.
     *
     * @return void
     */
    public function test_each_progress_bar_is_named_by_its_own_sentence(): void {
        $this->resetAfterTest();
        $xpath = $this->render_under_theme('tool_lp/template_statistics', $this->example_context('tool_lp/template_statistics'));

        $bars = $xpath->query("//progress");
        $this->assertSame(3, $bars->length);
        $names = [];
        foreach ($bars as $bar) {
            $label = $bar->parentNode;
            $this->assertSame('label', $label->nodeName);
            $this->assertFalse($bar->hasAttribute('aria-labelledby'));
            $names[] = trim((string) preg_replace('/\s+/u', ' ', $label->textContent));
        }
        $this->assertCount(3, array_unique($names));
        $this->assertSame(0, $xpath->query("//*[starts-with(@id, 'progress-')]")->length);
    }

    /**
     * The breakdown report's rating cell states proficiency in words and with an icon, never by colour alone.
     *
     * Core's cell was painted green when the rating was proficient and was otherwise identical. The
     * text of the three renders - what is left once the colours are gone, and what a screen reader
     * receives - must differ, each pill with an icon hidden from assistive technology beside its text.
     *
     * @return void
     */
    public function test_the_report_cell_says_proficiency_in_words(): void {
        $this->resetAfterTest();
        $cases = [
            'proficient' => [['grade' => 2, 'gradename' => 'Competent', 'proficiency' => true], 'Competent Proficient'],
            'not proficient' => [['grade' => 1, 'gradename' => 'Beginning', 'proficiency' => false],
                'Beginning Not proficient'],
            'not rated' => [['grade' => null, 'gradename' => '-', 'proficiency' => null], 'Not rated'],
        ];
        $texts = [];
        foreach ($cases as $case => [$context, $expected]) {
            $xpath = $this->render_under_theme('report_competency/user_competency_summary', $context);
            $text = self::text_of($xpath, "//a[@title='User competency summary']");
            $this->assertSame($expected, $text, "The {$case} cell reads wrong.");
            $pills = $xpath->query("//span[contains(@class, 'dim-comp-pill')]");
            $this->assertGreaterThan(0, $pills->length);
            foreach ($pills as $pill) {
                $this->assertSame(1, $xpath->query("./i[@aria-hidden='true']", $pill)->length, "A {$case} pill has no icon.");
                $this->assertNotSame('', trim($pill->textContent), "A {$case} pill has no text.");
            }
            $texts[$case] = $text;
        }

        $this->assertCount(3, array_unique($texts), 'Two states read alike once the colours are gone.');
    }

    /**
     * The user competency summary states proficiency with an icon per state, and keeps core's words.
     *
     * The dd after "Proficient" keeps core's proficiency name ("Yes", "No"), which tool_lp's Behat
     * reads, and adds a different icon per state, so proficient and not proficient differ by more than
     * the colour of the pill; not rated says so after "Rating" in place of core's dash.
     *
     * @return void
     */
    public function test_the_user_competency_summary_says_proficiency_with_an_icon(): void {
        $this->resetAfterTest();
        $base = $this->example_context('tool_lp/user_competency_summary');
        $states = [
            'proficient' => [['grade' => 2, 'gradename' => 'Competent', 'proficiency' => true, 'proficiencyname' => 'Yes'],
                'fa-circle-check', 'Competent'],
            'not proficient' => [['grade' => 1, 'gradename' => 'Beginning', 'proficiency' => false, 'proficiencyname' => 'No'],
                'fa-circle-exclamation', 'Beginning'],
            'not rated' => [['grade' => null, 'gradename' => '-', 'proficiency' => null, 'proficiencyname' => 'No'],
                'fa-circle-minus', 'Not rated'],
        ];
        $icons = [];
        foreach ($states as $state => [$values, $icon, $rating]) {
            $context = $base;
            $context['usercompetency'] = array_merge($context['usercompetency'], $values);
            $xpath = $this->render_under_theme('tool_lp/user_competency_summary', $context);

            $proficiency = "//dt[contains(., 'Proficient')]/following-sibling::dd[1]";
            $this->assertSame($values['proficiencyname'], self::text_of($xpath, $proficiency), "The {$state} dd lost core's name.");
            $iconed = $xpath->query($proficiency . "//i[@aria-hidden='true'][contains(@class, '{$icon}')]")->length;
            $this->assertSame(1, $iconed, "The {$state} pill does not carry {$icon}.");
            $ratingtext = self::text_of($xpath, "//dt[contains(., 'Rating')]/following-sibling::dd[1]");
            $this->assertStringContainsString($rating, $ratingtext);
            $icons[$state] = $icon;
        }

        $this->assertCount(3, array_unique($icons));
    }

    /**
     * The course summary draws its evidence rows itself, with the hooks core's evidence partial carries.
     *
     * Core's tool_lp/user_competency_summary_in_course includes tool_lp/evidence_summary once per piece of evidence; the
     * theme's copy draws a table row instead, so the partial's hooks are read from core's partial: the container with
     * its id, the trigger under core's candelete, and the JavaScript registering the two selectors.
     *
     * @return void
     */
    public function test_the_course_summary_keeps_the_evidence_partials_hooks(): void {
        global $CFG;
        $core = self::without_comments(file_get_contents($CFG->dirroot . '/admin/tool/lp/templates/evidence_summary.mustache'));
        $copy = self::without_comments(file_get_contents($this->theme_file('tool_lp/user_competency_summary_in_course')));
        $literals = ['data-region="evidence"', 'data-id="{{id}}"', '{{#candelete}}', 'data-action="delete-evidence"',
            'tool_lp/evidence_delete', "register('[data-action=\"delete-evidence\"]', '[data-region=\"evidence\"]');"];

        foreach ($literals as $literal) {
            $this->assertStringContainsString($literal, $core, "{$literal} is not in core's evidence partial: the list is wrong.");
            $this->assertStringContainsString($literal, $copy, "The course summary lost the evidence partial's {$literal}.");
        }
    }

    /**
     * Each evidence row of the course summary is the one element carrying data-region="evidence" above its trigger.
     *
     * tool_lp/evidence_delete takes the id from the ancestors of the trigger with that region and refuses two.
     *
     * @return void
     */
    public function test_the_course_summary_evidence_region_is_not_nested(): void {
        $this->resetAfterTest();
        $name = 'tool_lp/user_competency_summary_in_course';
        $xpath = $this->render_under_theme($name, $this->example_context($name));

        $this->assertSame(2, $xpath->query("//*[@data-region='evidence']")->length);
        $triggers = $xpath->query("//a[@data-action='delete-evidence']");
        $this->assertSame(2, $triggers->length);
        foreach ($triggers as $trigger) {
            $this->assertSame(1, $xpath->query("ancestor::*[@data-region='evidence']", $trigger)->length);
        }
        $this->assertSame(1, $xpath->query("//*[@data-node='user-competency']")->length);
    }

    /**
     * Rate is the page's one primary action, drawn only where core says the viewer may rate.
     *
     * The control is the same context with cangrade on: the button is there, and it is the only solid button.
     *
     * @return void
     */
    public function test_the_course_summary_offers_rate_only_with_cangrade(): void {
        $this->resetAfterTest();
        $name = 'tool_lp/user_competency_summary_in_course';
        $context = $this->example_context($name);

        $xpath = $this->render_under_theme($name, $context);
        $this->assertSame(1, $xpath->query("//button[starts-with(@id, 'rate_')]")->length, 'The control lost its Rate.');
        $this->assertSame(1, $xpath->query("//*[contains(concat(' ', @class, ' '), ' btn-primary ')]")->length);

        $context['usercompetencysummary']['cangrade'] = false;
        $xpath = $this->render_under_theme($name, $context);
        $this->assertSame(0, $xpath->query("//button[starts-with(@id, 'rate_')]")->length, 'Rate is offered without cangrade.');
        $this->assertSame(0, $xpath->query("//*[contains(concat(' ', @class, ' '), ' btn-primary ')]")->length);
        $this->assertSame(4, $xpath->query("//*[@data-region='dim-comp-ucc-band']//dt")->length, 'The band went with Rate.');
    }

    /**
     * Core's context alone, as tool_lp/user_competency_info re-renders it, still draws the band and the table.
     *
     * Without the renderer's dimband the two counts are empty until the template's JavaScript counts the rows it drew;
     * everything else is drawn from core's keys: the rating, proficiency, the most recent date and every row.
     *
     * @return void
     */
    public function test_the_course_summary_draws_from_cores_context_alone(): void {
        $this->resetAfterTest();
        $name = 'tool_lp/user_competency_summary_in_course';
        $context = $this->example_context($name);
        unset($context['dimband']);
        $xpath = $this->render_under_theme($name, $context);

        $tiles = "//*[@data-region='dim-comp-ucc-band']//div[contains(@class, 'dim-comp-ucc-tile')]";
        $this->assertSame(4, $xpath->query($tiles)->length);
        $this->assertSame('', self::text_of($xpath, "//dd[@data-dim-figure='evidence']"));
        $this->assertSame('', self::text_of($xpath, "//dd[@data-dim-figure='plans']"));
        $this->assertSame('Competent', self::text_of($xpath, "//dt[contains(., 'Rating')]/following-sibling::dd[1]"));
        $this->assertSame('Tuesday, 13 October 2026, 10:04', self::text_of($xpath, "//span[@data-dim-figure='latest']"));
        $this->assertSame(2, $xpath->query("//tbody[@data-region='dim-comp-ucc-rows']/tr[@data-region='evidence']")->length);

        // No evidence: the frame is hidden, the empty line shown, the most recent date hidden.
        $context['usercompetencysummary']['evidence'] = [];
        $xpath = $this->render_under_theme($name, $context);
        $this->assertSame(1, $xpath->query("//div[@data-region='dim-comp-ucc-frame'][@hidden]")->length);
        $this->assertSame(1, $xpath->query("//p[@data-region='dim-comp-ucc-empty'][not(@hidden)]")->length);
        $this->assertSame(1, $xpath->query("//dd[@data-region='dim-comp-ucc-latest'][@hidden]")->length);
    }

    /**
     * The template's JavaScript counts the band again on every render and after every deletion.
     *
     * Core's re-render carries no dimband and tool_lp/evidence_delete removes a row without re-rendering, so the counts
     * come from the rows: painted once per render (the js block runs again after each replace) and on each change of
     * the rows' list. PHPUnit runs no JavaScript; this reads the source.
     *
     * @return void
     */
    public function test_the_course_summary_recounts_its_band_in_the_browser(): void {
        $source = self::without_comments(file_get_contents($this->theme_file('tool_lp/user_competency_summary_in_course')));
        $js = preg_match('~\{\{#js\}\}(.*?)\{\{/js\}\}~s', $source, $matches) ? $matches[1] : '';

        $this->assertStringContainsString("var rows = competencyElement.find('[data-region=\"dim-comp-ucc-rows\"]');", $js);
        $this->assertStringContainsString("var evidence = rows.find('[data-region=\"evidence\"]');", $js);
        $this->assertStringContainsString("find('[data-dim-figure=\"evidence\"]').text(evidence.length);", $js);
        $plans = "find('[data-dim-figure=\"plans\"]').text(competencyElement.find('[data-dim-plan]').length);";
        $this->assertStringContainsString($plans, $js);
        $this->assertMatchesRegularExpression('~\n\s*paint\(\);\n~', $js, 'The band is not painted on each render.');
        $this->assertStringContainsString('new window.MutationObserver(paint).observe(rows.get(0), {childList: true});', $js);
        $this->assertStringContainsString('data-region="dim-comp-ucc-rows"', $source);
        $this->assertStringContainsString('data-dim-plan="{{id}}"', $source);
    }
}
