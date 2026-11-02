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
 * Theme Dimensions - The paging web service of the category showcase
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_course\customfield\course_handler;
use core_external\external_api;
use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use theme_dimensions\local\category\classifier;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\facets;
use theme_dimensions\local\category\guard;
use theme_dimensions\local\category\population;
use theme_dimensions\local\category\rows;
use theme_dimensions\local\landingfields;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../local/category/fixture.php');

/**
 * Tests for the one function the client asks for a page of cards.
 *
 * Everything goes through call_external_function(), never the bare static, so
 * every assertion is made on a payload clean_returnvalue() has already run
 * over: the returns declaration is an allowlist and a key it does not name is
 * dropped in silence, which is exactly the failure a test calling execute()
 * directly cannot see.
 *
 * Three properties are pinned here. The refusal matrix - every miss answering
 * with one exception and one message, with an admitted category beside it as
 * the control. The budget - the statements of a page flat in the size of the
 * subtree, as budget_test measures the decision layer it delegates to. And
 * the parity of the returns: every key of the card context is declared in the
 * returns and nothing else is, in both directions, because an undeclared key
 * is silently dropped on the way out and a declared one that the card stopped
 * exporting is a required key that will throw for everyone.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_category_rows::class)]
#[CoversClass(rows::class)]
final class get_category_rows_test extends \advanced_testcase {
    use \theme_dimensions\local\category\fixture;

    /** @var string The registered name of the function under test. */
    private const FUNCTION = 'theme_dimensions_get_category_rows';

    /**
     * Call the function the way the client does.
     *
     * @param array $args The arguments.
     * @return array The framework's response: error, data or exception.
     */
    private function call(array $args): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function(self::FUNCTION, $args, false);
    }

    /**
     * Call it and assert it answered.
     *
     * @param array $args The arguments.
     * @return array The cleaned payload.
     */
    private function data(array $args): array {
        $response = $this->call($args);
        $this->assertFalse(
            $response['error'],
            'The function refused: ' . ($response['exception']->message ?? '')
        );
        return $response['data'];
    }

    /**
     * Call it and assert it refused.
     *
     * @param array $args The arguments.
     * @return \stdClass The exception information the framework returns.
     */
    private function refusal(array $args): \stdClass {
        $response = $this->call($args);
        $this->assertTrue($response['error'], 'The function admitted what it had to refuse.');
        return $response['exception'];
    }

    /**
     * Start a fresh page, the way the web service framework does for every call.
     *
     * @return void
     */
    private function newpage(): void {
        global $PAGE;
        $PAGE = new \moodle_page();
    }

    /**
     * The refusal matrix: four ways to miss, one answer, and an admitted category as the control.
     */
    public function test_every_category_it_withholds_answers_alike(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $admitted = $generator->create_category();
        $generator->create_course(['category' => $admitted->id]);
        $hidden = $generator->create_category(['visible' => 0]);
        $unlisted = $generator->create_category();
        $this->set_category_state((int) $unlisted->id, category_discoverability::STATE_UNLISTED);
        $missing = (int) $unlisted->id + 1000;

        $viewer = $generator->create_user();
        $this->become($viewer);

        $refusals = [
            'a category id nothing holds' => $this->refusal(['categoryid' => $missing]),
            'a category core hides' => $this->refusal(['categoryid' => (int) $hidden->id]),
            'a category the plugin withholds' => $this->refusal(['categoryid' => (int) $unlisted->id]),
        ];
        $this->setGuestUser();
        self::forget();
        $refusals['the guest account'] = $this->refusal(['categoryid' => (int) $admitted->id]);

        $first = reset($refusals);
        foreach ($refusals as $case => $exception) {
            $this->assertSame(guard::ERROR_CATEGORY, $exception->errorcode, $case);
            $this->assertSame($first->message, $exception->message, $case);
        }

        // The control: the same call by the same viewer on an admitted category answers.
        $this->become($viewer);
        $data = $this->data(['categoryid' => (int) $admitted->id]);
        $this->assertSame(1, $data['total']);
    }

    /**
     * The cheap checks refuse a malformed request before anything is looked up.
     */
    public function test_a_malformed_request_is_refused_as_an_invalid_parameter(): void {
        $this->resetAfterTest();
        $category = self::getDataGenerator()->create_category();
        $this->become(self::getDataGenerator()->create_user());
        $id = (int) $category->id;

        $this->assertSame(
            'invalidparameter',
            $this->refusal(['categoryid' => $id, 'page' => -1])->errorcode,
            'A negative page.'
        );
        $this->assertSame(
            'invalidparameter',
            $this->refusal(['categoryid' => $id, 'chip' => 'nosuchchip'])->errorcode,
            'A chip outside the classifier vocabulary.'
        );
        /* The favourites are fav and never a chip here: the older spelling of them as a chip is read
           off an address alone (criteria::from_request()), and this function's one client never sends it. */
        $this->assertSame(
            'invalidparameter',
            $this->refusal(['categoryid' => $id, 'chip' => classifier::CHIP_FAVOURITES])->errorcode,
            'The favourites as a chip.'
        );
        $this->assertSame(
            'invalidparameter',
            $this->refusal(['categoryid' => $id, 'fav' => 'sometimes'])->errorcode,
            'The favourites toggle is a boolean.'
        );
        $this->assertSame(
            'invalidparameter',
            $this->refusal(['categoryid' => $id, 'filters' => [
                ['fieldid' => 7, 'value' => 1],
                ['fieldid' => 7, 'value' => 2],
            ]])->errorcode,
            'Two filters naming one field.'
        );
        $this->assertSame(
            'invalidparameter',
            $this->refusal(['categoryid' => $id, 'filters' => [['fieldid' => 999999, 'value' => 1]]])->errorcode,
            'A field outside the configured allowlist.'
        );
        $this->assertSame(
            'invalidparameter',
            $this->refusal(['categoryid' => $id, 'sort' => 'sideways'])->errorcode,
            'An organisation outside the allowlist.'
        );
        $this->assertSame(
            'invalidparameter',
            $this->refusal(['categoryid' => $id, 'datefrom' => '17/09/2026'])->errorcode,
            'A date in another calendar\'s spelling.'
        );
        $this->assertSame(
            'invalidparameter',
            $this->refusal(['categoryid' => $id, 'dateto' => '2026-09-31'])->errorcode,
            'A day September does not have.'
        );
        /* A pair given backwards: the page puts one in order (criteria), the module orders every pair
           before it asks, so one arriving here is a defect of the client and is refused. The same
           day on both sides is the one-day period, and is the control. */
        $this->assertSame(
            'invalidparameter',
            $this->refusal(['categoryid' => $id, 'datefrom' => '2026-09-19', 'dateto' => '2026-09-17'])->errorcode,
            'A pair of dates given backwards.'
        );
        $this->newpage();
        $this->data(['categoryid' => $id, 'datefrom' => '2026-09-17', 'dateto' => '2026-09-17']);
        $this->newpage();
        $this->data(['categoryid' => $id, 'fav' => true]);
    }

    /**
     * The root answers a search alone: the posters' numbers and the areas' word, and nothing of a listing.
     *
     * The root's search line is live, so this function serves category 0 - the course index every
     * logged-in account opens, answered from the system context - and it reads the term and nothing
     * else. Anything else sent with category 0 is refused as malformed, because the one client never
     * sends it; the guest is refused as everywhere. The root at rest is the control: it counts
     * nothing, so its answer names no area.
     */
    public function test_the_root_answers_a_search_alone(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $fire = $generator->create_category(['name' => 'Fire']);
        $rescue = $generator->create_category(['name' => 'Rescue']);
        $generator->create_course(['category' => $fire->id, 'fullname' => 'Zeta one']);
        $generator->create_course(['category' => $fire->id, 'fullname' => 'Zeta two']);
        $generator->create_course(['category' => $rescue->id, 'fullname' => 'Other']);
        $viewer = $generator->create_user();
        $this->become($viewer);

        $data = $this->data(['categoryid' => 0, 'query' => 'Zeta']);
        $this->assertSame(0, $data['categoryid']);
        $this->assertSame(2, $data['total'], 'The matches of the whole site.');
        $byid = array_column($data['accordion'], null, 'id');
        $this->assertSame(2, $byid[(int) $fire->id]['count']);
        $this->assertSame(0, $byid[(int) $rescue->id]['count'], 'An area the term misses counts 0.');
        $this->assertSame(
            get_string('category_results', 'theme_dimensions', 2),
            $byid[(int) $fire->id]['resultlabel'],
            'Each poster\'s pill.'
        );
        $this->assertTrue($byid[(int) $rescue->id]['expandable'], 'Every poster of the root opens, a zero one too.');
        $this->assertStringContainsString('q=Zeta', $byid[(int) $fire->id]['ctaurl'], 'The call to action carries the term.');
        $this->assertTrue($data['areas']['filtered']);
        $this->assertSame(0, $data['areas']['autoopen'], 'No area opens by itself at the root.');
        $this->assertSame(['', '', []], [$data['countnumber'], $data['countline'], $data['cards']], 'No listing.');
        $this->assertSame(['available' => false, 'pressed' => false, 'count' => 0], $data['favourites'], 'And no toggle.');

        $rest = $this->data(['categoryid' => 0]);
        $this->assertSame([], $rest['accordion'], 'At rest the root counts nothing.');
        $this->assertFalse($rest['areas']['filtered']);
        $this->assertSame(3, $rest['total']);

        $unread = [
            'a chip' => ['chip' => classifier::STATE_ENROLLED],
            'an organisation' => ['sort' => criteria::SORT_NAME],
            'a period' => ['datefrom' => '2026-09-01'],
            'a page' => ['page' => 1],
            'a page size' => ['perpage' => 6],
            'a panel\'s request' => ['withcategories' => true],
            'the favourites' => ['fav' => true],
        ];
        foreach ($unread as $case => $args) {
            $this->assertSame(
                'invalidparameter',
                $this->refusal(['categoryid' => 0, 'query' => 'Zeta'] + $args)->errorcode,
                'The root reads its search alone: ' . $case . ' is refused.'
            );
        }

        $this->setGuestUser();
        self::forget();
        $this->assertSame(guard::ERROR_CATEGORY, $this->refusal(['categoryid' => 0, 'query' => 'Zeta'])->errorcode);
    }

    /**
     * The organisation and the two bounds reach the population, and the returns are unchanged.
     *
     * The page drops a value it does not know and renders; this function
     * refuses one, because the only client it has is this theme's own module
     * and a request it never sends is a defect worth seeing. Both halves are
     * asserted - the refusal in
     * {@see test_a_malformed_request_is_refused_as_an_invalid_parameter()}, and
     * here that the values it does accept really reorder and really narrow,
     * which is what tells a parameter that arrived from a parameter that was
     * declared and then dropped on the way through.
     */
    public function test_the_organisation_and_the_dates_reach_the_listing(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $day = criteria::day_start('2026-09-17');
        $generator->create_course(['category' => $category->id, 'fullname' => 'Zulu', 'startdate' => $day]);
        $generator->create_course([
            'category' => $category->id,
            'fullname' => 'Alpha',
            'startdate' => $day + (30 * DAYSECS),
        ]);
        $this->become($generator->create_user());
        $id = (int) $category->id;

        $bynames = $this->data(['categoryid' => $id, 'sort' => 'name']);
        $this->assertSame(['Alpha', 'Zulu'], array_column($bynames['cards'], 'fullname'));

        $this->newpage();
        self::forget();
        $byrecent = $this->data(['categoryid' => $id, 'sort' => 'recent']);
        $this->assertSame(['Alpha', 'Zulu'], array_column($byrecent['cards'], 'fullname'), 'The newest start date first.');

        $this->newpage();
        self::forget();
        $narrowed = $this->data(['categoryid' => $id, 'datefrom' => '2026-09-17', 'dateto' => '2026-09-17']);
        $this->assertSame(1, $narrowed['total']);
        $this->assertSame(['Zulu'], array_column($narrowed['cards'], 'fullname'));
        $this->assertTrue($narrowed['hasfilters'], 'A date bound narrows the listing, so the way out is offered.');

        // Each bound reaches the listing on its own: a start alone keeps the later course, an end alone the earlier.
        $this->newpage();
        self::forget();
        $start = $this->data(['categoryid' => $id, 'sort' => 'name', 'datefrom' => '2026-09-18']);
        $this->assertSame(['Alpha'], array_column($start['cards'], 'fullname'), 'A start alone has no upper bound.');
        $this->assertSame(1, $start['filtercount']);
        $this->newpage();
        self::forget();
        $end = $this->data(['categoryid' => $id, 'sort' => 'name', 'dateto' => '2026-09-17']);
        $this->assertSame(['Zulu'], array_column($end['cards'], 'fullname'), 'An end alone has no lower bound.');
        $this->assertSame(1, $end['filtercount']);

        // The returns are the same allowlist they were: the card already carries its category's name.
        $this->assertArrayHasKey('overline', $narrowed['cards'][0]);
        $this->assertArrayNotHasKey('categoryid', $narrowed['cards'][0]);
    }

    /**
     * Every number the page prints is counted after both gates had their say.
     */
    public function test_the_total_counts_what_the_viewer_may_see_and_what_the_chip_kept(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $mine = $generator->create_course(['category' => $category->id]);
        $generator->create_course(['category' => $category->id]);
        $withheld = $generator->create_course(['category' => $category->id]);
        $this->set_course_state((int) $withheld->id, discoverability::STATE_UNLISTED);
        $viewer = $generator->create_user();
        $generator->enrol_user($viewer->id, $mine->id);
        $this->become($viewer);

        $all = $this->data(['categoryid' => (int) $category->id]);
        $this->assertSame(2, $all['total'], 'Three courses, one of them withheld from this viewer.');
        $this->assertCount(2, $all['cards']);

        $enrolled = $this->data(['categoryid' => (int) $category->id, 'chip' => classifier::STATE_ENROLLED]);
        $this->assertSame(1, $enrolled['total'], 'The chip narrows the total, not only the cards.');
        $this->assertCount(1, $enrolled['cards']);
        $this->assertSame((int) $mine->id, $enrolled['cards'][0]['id']);
    }

    /**
     * The next page has a real address, and the last page has none.
     */
    public function test_the_next_page_travels_as_an_address_and_stops_at_the_end(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        set_config(population::SETTING_PAGESIZE, 24, 'theme_dimensions');
        for ($i = 0; $i < 25; $i++) {
            $generator->create_course(['category' => $category->id]);
        }
        $this->become($generator->create_user());

        $first = $this->data(['categoryid' => (int) $category->id]);
        $this->assertTrue($first['hasmore']);
        $this->assertStringContainsString(criteria::PARAM_PAGE . '=1', $first['nexturl']);
        $this->assertCount(24, $first['cards']);

        $second = $this->data(['categoryid' => (int) $category->id, 'page' => 1]);
        $this->assertFalse($second['hasmore']);
        $this->assertSame('', $second['nexturl'], 'The last page names no next one.');
        $this->assertCount(1, $second['cards']);
    }

    /**
     * The page size the address asked for is the page size every later page is cut with.
     *
     * The showcase honours a perpage in its query string: the toolbar carries
     * it as a hidden field and every pager link re-emits it, so a listing can
     * be, and in Behat is, opened at a size the site setting never mentions.
     * Without the parameter this function answers page one of such a listing
     * at the site default instead - and because population::clamp_page() puts a
     * page past the end back onto the last one, the client asking for page one
     * of a six-row listing gets page nought of a forty-eight-row one: the whole
     * listing appended over the rows already on screen, duplicate element ids
     * and all, or forty-two rows skipped where the category is large. Nothing
     * in the payload says it happened.
     */
    public function test_the_page_size_the_address_asked_for_cuts_every_page(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $names = ['Alpha course', 'Beta course', 'Gamma course'];
        foreach ($names as $name) {
            $generator->create_course(['category' => $category->id, 'fullname' => $name]);
        }
        $this->become($generator->create_user());
        $id = (int) $category->id;

        $first = $this->data(['categoryid' => $id, 'perpage' => 1]);
        $this->assertSame(1, $first['pagesize']);
        $this->assertSame(0, $first['page']);
        $this->assertSame(3, $first['total']);
        $this->assertCount(1, $first['cards']);
        $this->assertTrue($first['hasmore']);

        $second = $this->data(['categoryid' => $id, 'perpage' => 1, 'page' => 1]);
        $this->assertSame(1, $second['page'], 'Page one of a listing cut at one row is page one.');
        $this->assertCount(1, $second['cards']);
        $this->assertNotSame(
            $first['cards'][0]['id'],
            $second['cards'][0]['id'],
            'The second page holds the SECOND course, not the listing over again.'
        );

        // The whole listing, page by page, is the whole listing exactly once.
        $third = $this->data(['categoryid' => $id, 'perpage' => 1, 'page' => 2]);
        $this->assertFalse($third['hasmore']);
        $seen = [$first['cards'][0]['id'], $second['cards'][0]['id'], $third['cards'][0]['id']];
        $this->assertCount(3, array_unique($seen), 'Three pages of one row each, three distinct courses.');

        // And the address the client hands paging back to carries the size with it.
        $this->assertStringContainsString(criteria::PARAM_PAGESIZE . '=1', $first['nexturl']);
    }

    /**
     * A page size above the cap is clamped rather than refused, as the address's own is.
     */
    public function test_an_outsized_page_size_is_clamped_to_the_maximum(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $generator->create_course(['category' => $category->id]);
        $this->become($generator->create_user());

        $data = $this->data(['categoryid' => (int) $category->id, 'perpage' => 100000]);
        $this->assertSame(population::MAX_PAGESIZE, $data['pagesize']);

        $default = $this->data(['categoryid' => (int) $category->id, 'perpage' => 0]);
        $this->assertSame(
            population::page_size(),
            $default['pagesize'],
            'Zero means the site default, which is what an address without a perpage gets.'
        );
    }

    /**
     * The facet half of the returns is declared against a toolbar that really has facets.
     *
     * facets::fields() is empty unless the site names live select fields in
     * categoryfilterfields, so every other test in this file answers with an
     * empty facet list and never compares the declaration with the producer at
     * all. Each of those keys is VALUE_REQUIRED: one renamed or dropped in
     * category_toolbar::facet_groups() is an invalid_response_exception for the
     * whole response, on every site that configures the setting and on no
     * other.
     */
    public function test_a_configured_facet_comes_back_whole_and_its_option_can_be_chosen(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        $fieldid = (int) landingfields::get_field(landingfields::SHORTNAME_MODALITY)->get('id');
        $online = $generator->create_course(['category' => $category->id]);
        $inperson = $generator->create_course(['category' => $category->id]);
        course_handler::create()->instance_form_save((object) [
            'id' => $online->id,
            'customfield_' . landingfields::SHORTNAME_MODALITY => 1,
        ]);
        course_handler::create()->instance_form_save((object) [
            'id' => $inperson->id,
            'customfield_' . landingfields::SHORTNAME_MODALITY => 2,
        ]);
        set_config(facets::SETTING, landingfields::SHORTNAME_MODALITY, 'theme_dimensions');
        $this->become($generator->create_user());
        $id = (int) $category->id;

        $data = $this->data(['categoryid' => $id]);
        $this->assertCount(1, $data['facets'], 'One allowlisted field, one group.');
        $facet = $data['facets'][0];
        $declared = array_keys(get_category_rows::execute_returns()->keys['facets']->content->keys);
        $exported = array_keys($facet);
        sort($declared);
        sort($exported);
        $this->assertSame($declared, $exported, 'The facet group and its declaration are the same set.');
        $this->assertSame(criteria::PARAM_FIELD_PREFIX . $fieldid, $facet['name']);
        $this->assertTrue($facet['anychecked'], 'With nothing chosen, the group\'s "all" is the checked one.');
        $this->assertNotEmpty($facet['options']);

        $optiondeclared = array_keys(
            get_category_rows::execute_returns()->keys['facets']->content->keys['options']->content->keys
        );
        $optionexported = array_keys($facet['options'][0]);
        sort($optiondeclared);
        sort($optionexported);
        $this->assertSame($optiondeclared, $optionexported);

        $chosen = $this->data([
            'categoryid' => $id,
            'filters' => [['fieldid' => $fieldid, 'value' => 1]],
        ]);
        $this->assertSame(1, $chosen['total'], 'The facet narrows the listing.');
        $this->assertSame((int) $online->id, $chosen['cards'][0]['id']);
        $picked = $chosen['facets'][0];
        $this->assertFalse($picked['anychecked']);
        $checked = array_column($picked['options'], 'checked', 'value');
        $this->assertTrue($checked[1], 'The chosen option comes back checked.');
    }

    /**
     * With the state group switched off the group is present and empty, never absent.
     *
     * The client paints one shape whatever the server offers, so the key is
     * always there; an empty option list is how it learns the toolbar draws no
     * state group at all, and the module's own guard reads it that way rather
     * than as "hide every pill".
     */
    public function test_the_state_group_is_present_and_empty_where_the_toolbar_offers_none(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $generator->create_course(['category' => $category->id]);
        set_config(population::SETTING_STATESORT, 0, 'theme_dimensions');
        $this->become($generator->create_user());

        $data = $this->data(['categoryid' => (int) $category->id]);
        $this->assertArrayHasKey('statusgroup', $data);
        $this->assertSame([], $data['statusgroup']['options'], 'No state group: an empty option list.');
        $this->assertSame(1, $data['total'], 'And the page still answers.');
    }

    /**
     * The toolbar the client repaints is the toolbar the page would have rendered.
     */
    public function test_the_option_lists_carry_every_pill_the_toolbar_would_draw(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $mine = $generator->create_course(['category' => $category->id]);
        $generator->create_course(['category' => $category->id]);
        $viewer = $generator->create_user();
        $generator->enrol_user($viewer->id, $mine->id);
        $this->become($viewer);

        $data = $this->data(['categoryid' => (int) $category->id]);
        $options = $data['statusgroup']['options'];
        $values = array_column($options, 'value');
        $this->assertSame('', $values[0], 'The "all" option comes first, as the template draws it.');
        $this->assertContains(classifier::STATE_ENROLLED, $values);
        $bystate = array_combine($values, $options);
        $this->assertSame(2, $bystate['']['count']);
        $this->assertTrue($bystate['']['checked'], 'With no chip asked for, "all" is the checked pill.');
        $this->assertSame(1, $bystate[classifier::STATE_ENROLLED]['count']);
        $this->assertTrue($bystate[classifier::STATE_ENROLLED]['hascount']);
        $declared = array_keys(get_category_rows::execute_returns()->keys['statusgroup']->keys['options']->content->keys);
        $exported = array_keys($options[0]);
        sort($declared);
        sort($exported);
        $this->assertSame($declared, $exported, 'The state pill and its declaration are the same set.');
        $this->assertNotContains(classifier::CHIP_FAVOURITES, $values, 'The favourites are no pill of the state group.');

        $picked = $this->data(['categoryid' => (int) $category->id, 'chip' => classifier::STATE_ENROLLED]);
        $checked = array_column($picked['statusgroup']['options'], 'checked', 'value');
        $this->assertTrue($checked[classifier::STATE_ENROLLED], 'The asked-for chip comes back checked.');
        $this->assertFalse($checked['']);
    }

    /**
     * The favourites toggle reaches the listing, combines with the chip, and comes back for the client to repaint.
     *
     * The answer carries the toggle as the toolbar would draw it - offered, pressed, and the number of
     * favourites the chip in force leaves - and every state pill counted under it. The request without
     * the toggle is the control; the SQL cut, which honours no favourites, offers none.
     */
    public function test_the_favourites_toggle_reaches_the_listing_and_comes_back(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $mine = $generator->create_course(['category' => $category->id]);
        $starred = $generator->create_course(['category' => $category->id]);
        $generator->create_course(['category' => $category->id]);
        $viewer = $generator->create_user();
        $generator->enrol_user($viewer->id, $mine->id);
        $generator->enrol_user($viewer->id, $starred->id);
        \core_favourites\service_factory::get_service_for_user_context(\core\context\user::instance($viewer->id))->create_favourite(
            'core_course',
            'courses',
            $starred->id,
            \core\context\course::instance($starred->id)
        );
        $this->become($viewer);
        $id = (int) $category->id;

        $plain = $this->data(['categoryid' => $id, 'sort' => criteria::SORT_NAME]);
        $this->assertSame(3, $plain['total'], 'The control: nothing narrows.');
        $this->assertSame(['available' => true, 'pressed' => false, 'count' => 1], $plain['favourites']);
        $this->assertFalse($plain['hasfilters']);

        $this->newpage();
        $favourites = $this->data(['categoryid' => $id, 'sort' => criteria::SORT_NAME, 'fav' => true]);
        $this->assertSame([(int) $starred->id], array_column($favourites['cards'], 'id'));
        $this->assertSame(['available' => true, 'pressed' => true, 'count' => 1], $favourites['favourites']);
        $this->assertTrue($favourites['hasfilters']);
        $this->assertSame(0, $favourites['filtercount'], 'The star shows them; the Filters badge does not count them.');
        $this->assertSame([], $favourites['chips'], 'And no chip: the star on the line already says so.');
        $this->assertFalse($favourites['clearall'], 'One criterion: its own control undoes it.');
        $pills = array_column($favourites['statusgroup']['options'], 'count', 'value');
        $this->assertSame(1, $pills[''], '"All" counted under the favourites.');
        $this->assertSame(1, $pills[classifier::STATE_ENROLLED], 'And the enrolled pill too.');

        $this->newpage();
        $both = $this->data([
            'categoryid' => $id,
            'sort' => criteria::SORT_NAME,
            'chip' => classifier::STATE_ENROLLED,
            'fav' => true,
        ]);
        $this->assertSame([(int) $starred->id], array_column($both['cards'], 'id'), 'The chip and the toggle combine.');
        $this->assertSame(0, $both['filtercount'], 'A scope value and the star are on the line, not behind the badge.');
        $this->assertTrue($both['clearall'], 'Two criteria: "Clear all" is offered.');
        $this->assertSame(1, $both['favourites']['count'], 'The favourites under the chip in force.');

        $this->newpage();
        set_config(population::SETTING_STATESORT, 0, 'theme_dimensions');
        $sqlcut = $this->data(['categoryid' => $id, 'sort' => criteria::SORT_NAME, 'fav' => true]);
        $this->assertSame(3, $sqlcut['total'], 'The SQL cut honours no favourites.');
        $this->assertSame(['available' => false, 'pressed' => false, 'count' => 0], $sqlcut['favourites'], 'And offers none.');
        $this->assertFalse($sqlcut['hasfilters']);
    }

    /**
     * A search term too short to search is reported rather than run.
     */
    public function test_a_search_term_below_the_floor_is_flagged_and_searched_for_nothing(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $generator->create_course(['category' => $category->id, 'fullname' => 'Astronomy']);
        $generator->create_course(['category' => $category->id, 'fullname' => 'Botany']);
        $this->become($generator->create_user());

        $short = $this->data(['categoryid' => (int) $category->id, 'query' => 'A']);
        $this->assertTrue($short['searchtooshort']);
        $this->assertSame(2, $short['total'], 'A term below the floor narrows nothing.');

        $real = $this->data(['categoryid' => (int) $category->id, 'query' => '  Astro  ']);
        $this->assertFalse($real['searchtooshort']);
        $this->assertSame(1, $real['total'], 'The server normalises the term; the client never has to.');
    }

    /**
     * A card's crests survive the returns, which is the only thing that keeps page 2 like page 1.
     *
     * clean_returnvalue() drops a key the declaration does not name, in silence: with badges
     * exported by the card and undeclared here, the server-rendered first page would carry the
     * crests and every page the client appended would carry none, on the same listing, with
     * nothing anywhere saying so. The key-set tests below are the structural guard; this one
     * asserts the value really arrives, which is what a reader of page two actually gets.
     */
    public function test_a_cards_crests_arrive_through_the_returns(): void {
        if (!landingfields::picture_type_available()) {
            $this->markTestSkipped('customfield_picture is not installed or not enabled.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id]);
        // A crest is drawn for a course carrying a landing page model only: the badge route serves no other.
        course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
        ]);
        $bare = $generator->create_course(['category' => $category->id, 'fullname' => 'Sem crest']);
        /** @var \core_customfield_generator $customfields */
        $customfields = $generator->get_plugin_generator('core_customfield');
        $data = $customfields->add_instance_data(
            landingfields::get_field(landingfields::SHORTNAME_CREST1),
            (int) $course->id,
            1
        );
        get_file_storage()->create_file_from_string([
            'contextid' => \core\context\course::instance((int) $course->id)->id,
            'component' => 'customfield_picture',
            'filearea' => 'file',
            'itemid' => (int) $data->get('id'),
            'filepath' => '/',
            'filename' => 'crest-alpha.png',
        ], base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

        $this->become($generator->create_user());
        $cards = array_column($this->data(['categoryid' => (int) $category->id])['cards'], null, 'id');

        $this->assertTrue($cards[(int) $course->id]['hasbadges']);
        $this->assertSame('crest alpha', $cards[(int) $course->id]['badges'][0]['alt']);
        $this->assertStringContainsString(
            '/theme_dimensions/badge/' . $course->id . '/',
            $cards[(int) $course->id]['badges'][0]['url'],
            'The address survives PARAM_URL, which is what the client puts in an img.'
        );
        $this->assertFalse($cards[(int) $bare->id]['hasbadges'], 'Control: the course beside it has none.');
        $this->assertSame([], $cards[(int) $bare->id]['badges']);
    }

    /**
     * Every key of the card context is declared in the returns, and nothing else is.
     */
    public function test_the_declared_card_keys_and_the_exported_card_keys_are_the_same_set(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $generator->create_course(['category' => $category->id]);
        $this->become($generator->create_user());

        $data = $this->data(['categoryid' => (int) $category->id]);
        $this->assertCount(1, $data['cards']);
        $exported = array_keys($data['cards'][0]);
        $declared = array_keys(get_category_rows::card_structure()->keys);
        sort($exported);
        sort($declared);

        $this->assertSame(
            $declared,
            $exported,
            'A key the returns does not name is dropped in silence; one it names that the card '
                . 'no longer exports throws for every reader.'
        );
        // The two labels the client relabels the star with.
        $this->assertContains('starlabeladd', $declared);
        $this->assertContains('starlabelremove', $declared);
    }

    /**
     * The card keys of the returns are the keys the exporter really produces.
     *
     * The test above reads the card through clean_returnvalue(), which can only
     * show a key the declaration dropped: an undeclared key never reaches the
     * payload, so comparing the payload with the declaration can never see it.
     * This one reads the delegate's own output, before the allowlist runs, so a
     * key added to the card and forgotten in the returns is caught at the
     * source rather than after every reader has silently lost it.
     */
    public function test_the_returns_name_every_key_the_card_exporter_produces(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $generator->create_course(['category' => $category->id]);
        $this->become($generator->create_user());

        $fetched = rows::fetch(\core_course_category::get((int) $category->id), []);
        $exported = array_keys($fetched['cards'][0]);
        $declared = array_keys(get_category_rows::card_structure()->keys);
        sort($exported);
        sort($declared);

        $this->assertSame($declared, $exported);
        // The dialogue's content is fetched when it opens (get_course_dialogue): no card carries it any more.
        foreach (['detailsid', 'excerpt', 'facts', 'learn', 'learnlabel'] as $gone) {
            $this->assertNotContains($gone, $declared, "The rows still carry {$gone}.");
        }

        // The badge is a structure of its own, and an allowlist one level down drops a key just as silently.
        $badge = array_keys((array) $fetched['cards'][0]['badge']);
        $declaredbadge = array_keys(get_category_rows::card_structure()->keys['badge']->keys);
        sort($badge);
        sort($declaredbadge);
        $this->assertSame($declaredbadge, $badge);
        $this->assertContains('icon', $declaredbadge);

        // So is the course dialogue's own structure, which every card draws onto its "More information" link.
        $modal = array_keys((array) $fetched['cards'][0]['modal']);
        $declaredmodal = array_keys(get_category_rows::card_structure()->keys['modal']->keys);
        sort($modal);
        sort($declaredmodal);
        $this->assertSame($declaredmodal, $modal);
        $this->assertContains('secondary', $declaredmodal);
        $this->assertNotContains('factslabel', $declaredmodal, 'The tiles\' label comes with the fetched content.');
    }

    /**
     * A conditional card's badge carries the prerequisite's name through the returns' allowlist, in one escape.
     *
     * The badge text is PARAM_TEXT, whose cleaning strips tags and throws for the whole response when the value
     * changes; the name holds a bare ampersand and a bare "<" followed by a space, the two characters that survive
     * format_string()'s tag stripping. The route keys travel too, empty on a card without a route.
     */
    public function test_a_conditional_badge_carries_the_prerequisite_through_the_returns(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $elsewhere = $generator->create_category();
        $prerequisite = $generator->create_course(['category' => $elsewhere->id, 'fullname' => 'Base & Prática < 2']);
        $course = $generator->create_course(['category' => $category->id]);
        $this->completed_instance($course, (int) $prerequisite->id);
        $viewer = $generator->create_user();
        $generator->enrol_user($viewer->id, $prerequisite->id);
        $this->become($viewer);

        $cards = array_column($this->data(['categoryid' => (int) $category->id])['cards'], null, 'id');

        $card = $cards[(int) $course->id];
        $this->assertSame('conditional', $card['state']);
        $this->assertSame(
            get_string('category_state_conditional', 'theme_dimensions', 'Base & Prática < 2'),
            $card['badge']['label']
        );
        $this->assertSame('fa-lock', $card['badge']['icon']);
        $this->assertFalse($card['hasroute']);
        $this->assertSame('', $card['routelabel']);
    }

    /**
     * The badge on the Filter summary counts this request's own filters, in both of its halves.
     *
     * The number on that badge is the only one of the toolbar a reader who
     * collapsed the filter panel can still see, so it has to move with every
     * request, as the rows, the count line and the clear link do. Because the
     * returns are an allowlist, a key they do not declare vanishes from the
     * payload without a trace - which is why both halves are asserted here
     * and the label is read for its number rather than for its wording.
     *
     * The bare request is the control: it proves the count is this request's
     * own and not a constant, since a service that always answered two would
     * pass the narrowed half alone.
     */
    public function test_the_filter_badge_counts_this_requests_own_filters(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $generator->create_course([
            'category' => $category->id,
            'fullname' => 'Zulu',
            'startdate' => criteria::day_start('2026-09-17'),
        ]);
        $this->become($generator->create_user());
        $id = (int) $category->id;

        $bare = $this->data(['categoryid' => $id]);
        $this->assertSame(0, $bare['filtercount'], 'Nothing narrows the page as it was loaded.');
        $this->assertStringContainsString('0', $bare['filtercountlabel']);

        $this->newpage();
        self::forget();
        $narrowed = $this->data([
            'categoryid' => $id,
            'query' => 'Zulu',
            'datefrom' => '2026-09-17',
            'dateto' => '2026-09-17',
        ]);
        $this->assertSame(
            1,
            $narrowed['filtercount'],
            'The whole period is one filter of the panel; the term is in its own box.'
        );
        $this->assertStringContainsString('1', $narrowed['filtercountlabel'], 'The sentence carries the same number.');
        $this->assertStringNotContainsString(
            '$a',
            $narrowed['filtercountlabel'],
            'The placeholder was substituted, not read out.'
        );
    }

    /**
     * The tree's category narrows a request.
     *
     * A cat naming a category below the listed one is a filter: the payload is narrowed, its
     * total follows and the row under the line names it in a chip. What the function does with a
     * cat that is not such a category is the next test's.
     *
     * The sibling's course is the control, and it is asserted from both sides
     * in one run - present without the criterion, absent with it - so a service
     * that ignored the parameter and a fixture that never held the course both
     * fail.
     */
    public function test_the_tree_criterion_narrows_a_request(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Saude']);
        $nursing = $generator->create_category(['parent' => $root->id, 'name' => 'Enfermagem']);
        $icu = $generator->create_category(['parent' => $nursing->id, 'name' => 'UTI']);
        $dentistry = $generator->create_category(['parent' => $root->id, 'name' => 'Odontologia']);
        $elsewhere = $generator->create_category(['name' => 'Outra arvore']);
        $intensive = $generator->create_course(['category' => $nursing->id, 'fullname' => 'Cuidados intensivos']);
        $ventilation = $generator->create_course(['category' => $icu->id, 'fullname' => 'Ventilacao mecanica']);
        $prosthesis = $generator->create_course(['category' => $dentistry->id, 'fullname' => 'Protese']);
        $this->become($generator->create_user());
        $id = (int) $root->id;

        $all = $this->data(['categoryid' => $id, 'sort' => criteria::SORT_NAME]);
        $this->assertContains(
            (int) $prosthesis->id,
            array_column($all['cards'], 'id'),
            'The control: the sibling\'s course is on the unnarrowed listing.'
        );
        $this->assertSame(3, $all['total']);
        $this->assertSame(0, $all['filtercount']);

        $this->newpage();
        self::forget();
        $narrowed = $this->data(['categoryid' => $id, 'sort' => criteria::SORT_NAME, 'cat' => (int) $nursing->id]);
        $ids = array_column($narrowed['cards'], 'id');
        $this->assertEqualsCanonicalizing(
            [(int) $intensive->id, (int) $ventilation->id],
            $ids,
            'The chosen category and everything below it.'
        );
        $this->assertNotContains((int) $prosthesis->id, $ids, 'And the sibling\'s course is gone.');
        $this->assertSame(2, $narrowed['total'], 'The number the count line prints follows.');
        $this->assertSame(0, $narrowed['filtercount'], 'The tree shows it, so the Filters badge does not count it.');
        $this->assertTrue($narrowed['hasfilters'], 'So the row under the line shows.');
        $this->assertSame(
            [['param' => criteria::PARAM_CAT, 'label' => 'Enfermagem']],
            array_map(
                static fn(array $chip): array => ['param' => $chip['param'], 'label' => $chip['label']],
                $narrowed['chips']
            ),
            'And its chip names the category, as the tree names it, so the way out of it is offered.'
        );
    }

    /**
     * A tree criterion the page does not offer is dropped, and every such id answers alike.
     *
     * The oracle this pins shut: refusing a category that is not below the listed one while
     * dropping one that is below it but withheld told an account which ids exist in a subtree it
     * may not browse. Now a nonexistent id, a category of another tree, a withheld descendant (core
     * hides it, the plugin does not list it), an id past anything the site holds, the listed
     * category itself and no criterion at all answer the same bytes, and the answer says no filter
     * is in force (the badge, the clear link and the next page's address are the unfiltered
     * ones). The visible descendant is the control, and the preconditions are asserted so a fixture
     * that withheld nothing cannot pass.
     *
     * Every request is in a flat order, because the grouped default drops `cat` at construction
     * and would answer alike with the guard back in place.
     *
     * @param bool $statesort The page-cutting mode.
     */
    #[DataProvider('statesort_provider')]
    public function test_a_tree_criterion_the_page_does_not_offer_is_dropped_alike(bool $statesort): void {
        global $DB;
        $this->resetAfterTest();
        set_config(population::SETTING_STATESORT, $statesort ? '1' : '0', 'theme_dimensions');
        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Saude']);
        $visible = $generator->create_category(['parent' => $root->id, 'name' => 'Enfermagem']);
        $hidden = $generator->create_category(['parent' => $root->id, 'name' => 'Oculta', 'visible' => 0]);
        $unlisted = $generator->create_category(['parent' => $root->id, 'name' => 'Fora da lista']);
        $outside = $generator->create_category(['name' => 'Outra arvore']);
        $generator->create_course(['category' => $root->id, 'fullname' => 'Curso da raiz']);
        $generator->create_course(['category' => $visible->id, 'fullname' => 'Curso visivel']);
        $generator->create_course(['category' => $hidden->id, 'fullname' => 'Curso na oculta', 'visible' => 1]);
        $generator->create_course(['category' => $unlisted->id, 'fullname' => 'Curso fora da lista']);
        $generator->create_course(['category' => $outside->id, 'fullname' => 'Curso de fora']);
        $this->set_category_state((int) $unlisted->id, category_discoverability::STATE_UNLISTED);
        $deleted = $generator->create_category(['parent' => $root->id, 'name' => 'Apagada']);
        $deletedid = (int) $deleted->id;
        $this->setAdminUser();
        \core_course_category::get($deletedid)->delete_full(false);
        $this->become($generator->create_user());
        $id = (int) $root->id;

        $ask = function (array $extra) use ($id): string {
            $this->newpage();
            self::forget();
            return json_encode($this->data(['categoryid' => $id, 'sort' => criteria::SORT_NAME] + $extra));
        };

        $plain = $ask([]);
        $answer = json_decode($plain, true);
        $this->assertGreaterThan(0, $answer['total'], 'Precondition: the unnarrowed listing holds courses.');
        $this->assertFalse($answer['hasfilters'], 'Precondition: nothing narrows it.');
        $this->assertSame(0, $answer['filtercount']);
        $names = array_column($answer['cards'], 'fullname');
        $this->assertNotContains('Curso na oculta', $names, 'Precondition: the hidden descendant is withheld from this viewer.');
        $this->assertNotContains('Curso fora da lista', $names, 'Precondition: so is the unlisted one.');
        $this->assertNotNull(
            \core_course_category::get((int) $outside->id, IGNORE_MISSING, true),
            'Precondition: the other tree exists.'
        );
        $this->assertNull(\core_course_category::get($deletedid, IGNORE_MISSING, true), 'Precondition: the deleted id is gone.');

        $dropped = [
            'an id nothing holds' => (int) $DB->get_field_sql('SELECT MAX(id) FROM {course_categories}') + 1000,
            'an id past anything the site holds' => 2000000000,
            'a deleted category' => $deletedid,
            'a category of another tree' => (int) $outside->id,
            'a descendant core hides' => (int) $hidden->id,
            'a descendant the plugin does not list' => (int) $unlisted->id,
            'the listed category itself' => $id,
        ];
        foreach ($dropped as $case => $cat) {
            $this->assertSame($plain, $ask(['cat' => $cat]), $case . ': the unnarrowed answer, byte for byte.');
        }

        // The control: a visible descendant narrows the listing, so the identity above is not vacuous.
        $narrowed = json_decode($ask(['cat' => (int) $visible->id]), true);
        $this->assertSame(['Curso visivel'], array_column($narrowed['cards'], 'fullname'));
        $this->assertLessThan($answer['total'], $narrowed['total']);
        $this->assertSame(0, $narrowed['filtercount'], 'The tree is not behind the badge.');
        $this->assertSame([criteria::PARAM_CAT], array_column($narrowed['chips'], 'param'));
        $this->assertTrue($narrowed['hasfilters']);
    }

    /**
     * The two page-cutting modes, as the population's own tests run them.
     *
     * @return array The cases.
     */
    public static function statesort_provider(): array {
        return ['PHP cut' => [true], 'SQL cut' => [false]];
    }

    /**
     * A panel asks for its area's subtree, flat, and gets its own context beside the cards.
     *
     * The web service half of the panels between the rows of posters. The grandchild's course is
     * the control of the subtree - it is in the panel, where the direct cut would keep it out - and
     * the subarea holding it is the strip's one pill, with its own colour. The panel's context is the
     * page's own builder without the cards (they come back once, at the top level) and without
     * the no-JavaScript addresses, which belong to a page the service does not know. The request
     * without `withcategories` is the control of the key itself.
     */
    public function test_a_panel_asks_for_the_subtree_and_gets_its_own_context(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Saude']);
        $nursing = $generator->create_category(['parent' => $root->id, 'name' => 'Enfermagem & Cia']);
        $icu = $generator->create_category(['parent' => $nursing->id, 'name' => 'UTI']);
        $intensive = $generator->create_course(['category' => $nursing->id, 'fullname' => 'Cuidados intensivos']);
        $wounds = $generator->create_course(['category' => $nursing->id, 'fullname' => 'Feridas']);
        $ventilation = $generator->create_course(['category' => $icu->id, 'fullname' => 'Ventilacao mecanica']);
        $this->setAdminUser();
        \theme_dimensions\local\category\branding::save((int) $icu->id, (object) [
            'bgcolour' => '#B3261E',
            'inkcolour' => '#ffffff',
        ]);
        $this->become($generator->create_user());

        $response = $this->data([
            'categoryid' => (int) $nursing->id,
            'sort' => 'recent',
            'perpage' => population::PANEL_SIZE,
            'withcategories' => true,
        ]);
        $ids = array_column($response['cards'], 'id');
        $this->assertEqualsCanonicalizing([(int) $intensive->id, (int) $wounds->id, (int) $ventilation->id], $ids);
        $this->assertSame(3, $response['total'], 'The whole subtree.');
        $this->assertSame([], $response['accordion'], 'A panel is one poster of somebody else\'s page.');
        $this->assertArrayNotHasKey('categories', $response, 'The payload carries no category cards.');
        foreach ($response['cards'] as $card) {
            $this->assertTrue($card['hasoverline'], 'Every mini names its area, the listed one\'s own courses included.');
        }

        $panel = $response['panel'];
        $this->assertSame((int) $nursing->id, $panel['id']);
        $this->assertSame('Enfermagem & Cia', $panel['name'], 'Plain: the sink escapes it.');
        $this->assertSame(
            \theme_dimensions\local\landing\links::showcase((int) $nursing->id, [], false)->out(false),
            $panel['titleurl'],
            'The title opens the area in its default organisation.'
        );
        $this->assertSame(get_string('category_panel_count', 'theme_dimensions', '3'), $panel['subtitle']);
        $this->assertFalse($panel['psortname']);
        foreach (['sorturlrecent', 'sorturlname', 'closeurl'] as $nojs) {
            $this->assertSame('', $panel[$nojs], 'The client draws buttons there: ' . $nojs . ' is empty.');
        }
        $this->assertTrue($panel['also']['has']);
        $this->assertSame(
            [['id' => (int) $icu->id, 'name' => 'UTI', 'haslink' => true, 'style' => '--dim-dot: #b3261e;']],
            array_map(
                static fn(array $pill): array => [
                    'id' => $pill['id'],
                    'name' => $pill['name'],
                    'haslink' => $pill['haslink'],
                    'style' => $pill['style'],
                ],
                $panel['also']['pills']
            ),
            'One pill per subarea, in its own colour.'
        );
        $this->assertStringContainsString('categoryid=' . $icu->id, $panel['also']['pills'][0]['url']);
        $this->assertTrue($panel['cta']['has']);
        $this->assertStringContainsString('categoryid=' . $nursing->id, $panel['cta']['url']);
        $this->assertStringContainsString('sort=recent', $panel['cta']['url']);
        $this->assertSame(
            get_string('category_panel_cta', 'theme_dimensions', 'Enfermagem & Cia'),
            $panel['cta']['label']
        );
        $this->assertTrue($panel['isrows']);
        $this->assertFalse($panel['isempty'] || $panel['iszero'] || $panel['isd20'] || $panel['jsmode']);

        // The A-Z panel is the same request in the other order, and its way out keeps it.
        $this->newpage();
        $atoz = $this->data(['categoryid' => (int) $nursing->id, 'sort' => 'name', 'withcategories' => true]);
        $this->assertTrue($atoz['panel']['psortname']);
        $this->assertStringContainsString('sort=name', $atoz['panel']['cta']['url']);

        // Control: the same category without the switch carries no panel at all.
        $this->newpage();
        $whole = $this->data(['categoryid' => (int) $nursing->id, 'sort' => 'name']);
        $this->assertArrayNotHasKey('panel', $whole, 'The panel is drawn only for a panel\'s request.');
        $this->assertSame(3, $whole['total']);
    }

    /**
     * The service lists a category's whole subtree and takes no direct cut: an old client's key is refused.
     *
     * A client still running the previous script sends `direct` and is refused as an unknown
     * parameter until its script revision changes. The default is the control: with no such key the
     * grandchild's course, which a direct cut would drop, is listed.
     */
    public function test_the_service_lists_the_subtree_and_refuses_a_direct_key(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $nursing = $generator->create_category(['name' => 'Enfermagem']);
        $icu = $generator->create_category(['parent' => $nursing->id, 'name' => 'UTI']);
        $own = $generator->create_course(['category' => $nursing->id]);
        $deep = $generator->create_course(['category' => $icu->id]);
        $this->become($generator->create_user());

        $this->assertEqualsCanonicalizing(
            [(int) $own->id, (int) $deep->id],
            array_column($this->data(['categoryid' => (int) $nursing->id, 'sort' => 'name'])['cards'], 'id'),
            'The whole subtree, the grandchild\'s course included.'
        );
        $this->newpage();
        $this->refusal(['categoryid' => (int) $nursing->id, 'direct' => true]);
    }

    /**
     * The accordion headers come back with the page, so a filter change can repaint their numbers.
     *
     * Without them the client would have to ask for every panel after every
     * keystroke to find out what its header should say, or leave the numbers
     * stale beside a page that had just changed. The flat request in the same
     * run is the control: a payload that always carried the headers would say
     * nothing about the grouped organisation.
     */
    public function test_the_accordion_headers_come_back_so_the_client_can_repaint_them(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Saude']);
        $nursing = $generator->create_category(['parent' => $root->id, 'name' => 'Enfermagem', 'sortorder' => 10]);
        $icu = $generator->create_category(['parent' => $nursing->id, 'name' => 'UTI']);
        $generator->create_course(['category' => $root->id, 'fullname' => 'Primeiros socorros']);
        $generator->create_course(['category' => $nursing->id, 'fullname' => 'Cuidados intensivos']);
        $generator->create_course(['category' => $icu->id, 'fullname' => 'Ventilacao mecanica']);
        $this->become($generator->create_user());

        $grouped = $this->data(['categoryid' => (int) $root->id]);
        $this->assertSame(
            [['Enfermagem', 2, true]],
            array_map(
                static fn(array $entry): array => [$entry['name'], $entry['count'], $entry['linkable']],
                $grouped['accordion']
            ),
            'One header per direct child, counting its whole subtree.'
        );
        $this->assertSame((int) $nursing->id, $grouped['accordion'][0]['id']);
        $this->assertSame(1, $grouped['total'], 'The page itself is the direct courses.');
        /* The shell a panel draws before its rows arrive: the call to action under this request,
           in the newest-first order, labelled with the poster's own count. */
        $this->assertSame(
            \theme_dimensions\local\landing\links::showcase((int) $nursing->id, ['sort' => 'recent'], false)->out(false),
            $grouped['accordion'][0]['ctaurl']
        );
        $this->assertSame(
            get_string('category_panel_cta', 'theme_dimensions', 'Enfermagem'),
            $grouped['accordion'][0]['ctalabel']
        );
        $this->newpage();
        $searched = $this->data(['categoryid' => (int) $root->id, 'query' => 'Cuidados']);
        $this->assertStringContainsString('q=Cuidados', $searched['accordion'][0]['ctaurl'], 'It carries what narrows the page.');

        $this->newpage();
        $flat = $this->data(['categoryid' => (int) $root->id, 'sort' => 'name']);
        $this->assertSame([], $flat['accordion'], 'Control: a flat organisation has no headers to repaint.');
        $this->assertSame(3, $flat['total']);
    }

    /**
     * A narrowed grouped answer carries the posters' pills, the count line and the areas' sentences.
     *
     * The client repaints the posters after a filter change from this answer alone and composes
     * nothing: the pill's words, the flip of an empty poster into a panel, the count line's number
     * and sentence with its "see all as a list", and the areas' result-mode data - the summary's
     * sentences, the zero panel's "elsewhere", "nothing anywhere" and the one area to open. The
     * same page at rest and a flat page are the controls: no pill, no result mode.
     */
    public function test_a_narrowed_grouped_answer_carries_the_pills_the_count_line_and_the_areas_sentences(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Saude']);
        $nursing = $generator->create_category(['parent' => $root->id, 'name' => 'Enfermagem']);
        $surgery = $generator->create_category(['parent' => $root->id, 'name' => 'Cirurgia']);
        $generator->create_category(['parent' => $root->id, 'name' => 'Vazia']);
        $generator->create_course(['category' => $root->id, 'fullname' => 'Primeiros socorros']);
        $generator->create_course(['category' => $nursing->id, 'fullname' => 'Cuidados intensivos']);
        $generator->create_course(['category' => $surgery->id, 'fullname' => 'Sutura']);
        $this->become($generator->create_user());
        $str = static fn(string $key, $a = null): string => get_string($key, 'theme_dimensions', $a);
        $links = \theme_dimensions\local\landing\links::class;
        $pills = static fn(array $data): array => array_map(
            static fn(array $entry): array => [$entry['name'], $entry['resultlabel'], $entry['resultcount'], $entry['expandable']],
            $data['accordion']
        );

        $rest = $this->data(['categoryid' => (int) $root->id]);
        $this->assertSame(
            [['Enfermagem', '', '', true], ['Cirurgia', '', '', true], ['Vazia', '', '', false]],
            $pills($rest),
            'At rest: no pill, and the empty area is a link.'
        );
        $this->assertSame(
            ['3', $str('category_countline', 'Saude'), ''],
            [$rest['countnumber'], $rest['countline'], $rest['seeallurl']]
        );
        $this->assertFalse($rest['areas']['filtered']);
        $this->assertSame(['', '', '', 0, false, 0, ''], [
            $rest['areas']['nomatchlabelall'],
            $rest['areas']['nomatchlabelless'],
            $rest['areas']['elsewhere'],
            $rest['areas']['moreresults'],
            $rest['areas']['allempty'],
            $rest['areas']['autoopen'],
            $rest['areas']['autoopenlabel'],
        ]);

        $this->newpage();
        $searched = $this->data(['categoryid' => (int) $root->id, 'query' => 'Cuidados']);
        $this->assertSame(
            [
                ['Enfermagem', $str('category_results_one'), '1', true],
                ['Cirurgia', $str('category_results', '0'), '0', true],
                ['Vazia', $str('category_results', '0'), '0', true],
            ],
            $pills($searched),
            'Under the search every poster wears its number, and the empty one opens.'
        );
        $this->assertSame(
            [
                '1',
                'course for “Cuidados”: 1 in 1 area',
                $links::showcase((int) $root->id, ['q' => 'Cuidados', 'sort' => 'recent'], false)->out(false),
            ],
            [$searched['countnumber'], $searched['countline'], $searched['seeallurl']]
        );
        $areas = $searched['areas'];
        $this->assertTrue($areas['filtered']);
        $this->assertSame($str('category_nomatch', (object) ['term' => 'Cuidados', 'count' => '2']), $areas['nomatchlabelall']);
        $this->assertSame($str('category_nomatch_one', 'Cuidados'), $areas['nomatchlabelless']);
        $this->assertSame($str('category_panel_zeroelsewhere_one'), $areas['elsewhere']);
        $this->assertFalse($areas['allempty']);
        $this->assertSame((int) $nursing->id, $areas['autoopen'], 'Nothing here and one area holding the result: it opens.');
        $this->assertSame($str('category_countline_autoopened', 'Enfermagem'), $areas['autoopenlabel']);

        $this->newpage();
        $this->assertTrue(
            $this->data(['categoryid' => (int) $root->id, 'query' => 'zzzz'])['areas']['allempty'],
            'Nothing anywhere.'
        );

        $this->newpage();
        $flat = $this->data(['categoryid' => (int) $root->id, 'sort' => 'name', 'query' => 'Cuidados']);
        $this->assertFalse($flat['areas']['filtered'], 'Control: a flat page has no areas to narrow.');
        $this->assertSame('', $flat['seeallurl'], 'And offers no list: it is one.');
        $this->assertSame('course for “Cuidados” in Saude', $flat['countline']);
    }

    /**
     * A search term holding a bracket crosses every sentence that carries it, and the panel speaks of results.
     *
     * The term arrives PARAM_RAW_TRIMMED and "<3" is a search; a PARAM_TEXT field carrying it
     * would throw invalid_response_exception for the whole answer, since strip_tags() changes it.
     * The count line, the summary's sentences, the panel's subtitle and its zero state all carry
     * it. The panel under a criterion counts results - its subtitle, its tile and its call to
     * action - and its strip offers only the subareas holding one, each carrying the criterion,
     * with the same panel at rest as the control.
     */
    public function test_a_term_holding_a_bracket_crosses_every_sentence_and_a_panel_speaks_of_results(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Saude']);
        $nursing = $generator->create_category(['parent' => $root->id, 'name' => 'Enfermagem & Cia']);
        $surgery = $generator->create_category(['parent' => $root->id, 'name' => 'Cirurgia']);
        for ($i = 0; $i < 10; $i++) {
            $generator->create_course(['category' => $nursing->id, 'fullname' => 'Cuidados <3 ' . $i]);
        }
        $subs = [];
        for ($i = 0; $i < 7; $i++) {
            $sub = $generator->create_category(['parent' => $nursing->id, 'name' => 'Sub ' . $i]);
            $generator->create_course(['category' => $sub->id, 'fullname' => 'Cuidados <3 sub ' . $i]);
            $subs[] = (int) $sub->id;
        }
        $other = $generator->create_category(['parent' => $nursing->id, 'name' => 'Outra']);
        $generator->create_course(['category' => $other->id, 'fullname' => 'Curativo']);
        $generator->create_course(['category' => $surgery->id, 'fullname' => 'Sutura']);
        $this->become($generator->create_user());
        $str = static fn(string $key, $a = null): string => get_string($key, 'theme_dimensions', $a);
        $links = \theme_dimensions\local\landing\links::class;

        $grouped = $this->data(['categoryid' => (int) $root->id, 'query' => '<3']);
        $this->assertSame('courses for “<3”: 17 in 1 area', $grouped['countline'], 'The count line carries the term.');
        $this->assertSame($str('category_nomatch_one', '<3'), $grouped['areas']['nomatchlabelall'], 'And the summary.');

        $this->newpage();
        $panel = $this->data([
            'categoryid' => (int) $nursing->id,
            'sort' => 'recent',
            'perpage' => population::PANEL_SIZE,
            'withcategories' => true,
            'query' => '<3',
        ])['panel'];
        $this->assertSame(
            $str('category_panel_showingresults', (object) ['shown' => '8', 'total' => '17', 'term' => '<3']),
            $panel['subtitle']
        );
        $this->assertSame(['9', $str('category_panel_moretileresults')], [$panel['tile']['count'], $panel['tile']['label']]);
        $this->assertSame(
            $str('category_panel_cta_moreresults', (object) ['name' => 'Enfermagem & Cia', 'count' => '9']),
            $panel['cta']['label'],
            'Its call to action counts results.'
        );
        $this->assertSame(
            array_slice($subs, 0, 6),
            array_column($panel['also']['pills'], 'id'),
            'The six first subareas with a result.'
        );
        foreach ($panel['also']['pills'] as $pill) {
            $this->assertStringContainsString('q=%3C3', $pill['url'], 'Each carrying the criterion.');
        }
        $this->assertSame(
            ['1', true],
            [$panel['also']['morecount'], $panel['also']['hasmore']],
            'The seventh is past the six; "Outra" matches nothing and is not counted.'
        );
        $this->assertSame(
            $links::showcase((int) $nursing->id, ['q' => '<3'], false)->out(false),
            $panel['also']['moreurl'],
            'And "+1" carries it to the area\'s page.'
        );

        $this->newpage();
        $zero = $this->data([
            'categoryid' => (int) $surgery->id,
            'sort' => 'recent',
            'withcategories' => true,
            'query' => '<3',
        ])['panel'];
        $this->assertTrue($zero['iszero']);
        $this->assertSame($str('category_panel_zero', (object) ['term' => '<3', 'name' => 'Cirurgia']), $zero['zero']);

        $this->newpage();
        $rest = $this->data([
            'categoryid' => (int) $nursing->id,
            'sort' => 'recent',
            'perpage' => population::PANEL_SIZE,
            'withcategories' => true,
        ])['panel'];
        $this->assertSame($str('category_panel_showing', (object) ['shown' => '8', 'total' => '18']), $rest['subtitle']);
        $this->assertSame(
            $str('category_panel_cta_more', (object) ['name' => 'Enfermagem & Cia', 'count' => '10']),
            $rest['cta']['label'],
            'Control: at rest it counts courses.'
        );
        $this->assertSame($str('category_panel_moretile'), $rest['tile']['label']);
        $this->assertSame(['2', ''], [$rest['also']['morecount'], $rest['zero']], 'Every subarea at rest, and no zero sentence.');
        $this->assertSame($rest['titleurl'], $rest['also']['moreurl']);
    }

    /**
     * The areas' sentences and the accordion's entries are declared key for key, in both directions.
     *
     * The same pair of tests as the card's and the panel's, over the `areas` structure and each
     * accordion entry.
     */
    public function test_the_declared_areas_and_accordion_keys_and_the_built_ones_are_the_same_set(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $child = $generator->create_category(['parent' => $root->id]);
        $generator->create_course(['category' => $child->id, 'fullname' => 'Cuidados']);
        $this->become($generator->create_user());

        $through = $this->data(['categoryid' => (int) $root->id, 'query' => 'Cuidados']);
        $this->newpage();
        $built = rows::fetch(\core_course_category::get((int) $root->id), ['query' => 'Cuidados']);
        $returns = get_category_rows::execute_returns();
        $pairs = [
            'the areas' => [get_category_rows::areas_structure()->keys, $through['areas'], $built['areas']],
            'an accordion entry' => [$returns->keys['accordion']->content->keys, $through['accordion'][0], $built['accordion'][0]],
        ];
        foreach ($pairs as $what => [$declared, $cleaned, $raw]) {
            $declared = array_keys($declared);
            $cleaned = array_keys($cleaned);
            $raw = array_keys($raw);
            sort($declared);
            sort($cleaned);
            sort($raw);
            $this->assertSame($declared, $cleaned, $what . ': a key the returns does not name is dropped in silence.');
            $this->assertSame($declared, $raw, $what . ': and the builder produces exactly what is declared.');
        }
        foreach (['countnumber', 'countline', 'seeallurl', 'areas'] as $key) {
            $this->assertArrayHasKey($key, $returns->keys, $key . ' is declared.');
            $this->assertArrayHasKey($key, $built, $key . ' is built.');
        }
    }

    /**
     * Every key of the panel's context is declared in the returns, and nothing else is.
     *
     * The card's own pair of tests, over the panel's context: the first direction reads the
     * payload through clean_returnvalue(), which can only show a key the declaration dropped,
     * and the second reads the builder's own output before the allowlist runs, which is
     * the only place an undeclared key is still visible at all. The nested structures are
     * compared as well, since each is an allowlist of its own.
     */
    public function test_the_declared_panel_keys_and_the_built_panel_keys_are_the_same_set(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $parent = $generator->create_category();
        $child = $generator->create_category(['parent' => $parent->id, 'name' => 'UTI']);
        for ($i = 0; $i < 10; $i++) {
            $generator->create_course(['category' => $i % 2 ? $parent->id : $child->id]);
        }
        $this->become($generator->create_user());

        $args = [
            'categoryid' => (int) $parent->id,
            'sort' => 'recent',
            'perpage' => population::PANEL_SIZE,
            'withcategories' => true,
        ];
        $through = $this->data($args)['panel'];
        $this->assertTrue($through['tile']['has'], 'Precondition: the tile is drawn, so its keys hold something.');
        $this->assertTrue($through['also']['has'], 'And the strip.');
        $structure = get_category_rows::panel_structure();

        $this->newpage();
        $built = rows::fetch(\core_course_category::get((int) $parent->id), [
            'sort' => 'recent',
            'pagesize' => population::PANEL_SIZE,
            'withcategories' => true,
        ])['panel'];

        $pairs = [
            'the panel' => [$structure->keys, $through, $built],
            'the strip' => [$structure->keys['also']->keys, $through['also'], $built['also']],
            'a pill' => [
                $structure->keys['also']->keys['pills']->content->keys,
                $through['also']['pills'][0],
                $built['also']['pills'][0],
            ],
            'the tile' => [$structure->keys['tile']->keys, $through['tile'], $built['tile']],
            'the call to action' => [$structure->keys['cta']->keys, $through['cta'], $built['cta']],
            'the list link' => [$structure->keys['seeaslist']->keys, $through['seeaslist'], $built['seeaslist']],
        ];
        foreach ($pairs as $what => [$declared, $cleaned, $raw]) {
            $declared = array_keys($declared);
            $cleaned = array_keys($cleaned);
            $raw = array_keys($raw);
            sort($declared);
            sort($cleaned);
            sort($raw);
            $this->assertSame(
                $declared,
                $cleaned,
                $what . ': a key the returns does not name is dropped in silence; one it names that the '
                    . 'builder no longer produces throws for every reader.'
            );
            $this->assertSame($declared, $raw, $what . ': and the builder produces exactly what is declared.');
        }
    }

    /**
     * The statements of one page do not grow with the size of the subtree.
     *
     * The same property budget_test measures over the decision layer, measured
     * here over the delegate the web service actually calls - which adds the
     * listing's four bulk reads, the card exports and the renderer, and must
     * add nothing per course on top of them.
     *
     * The slope is measured between two listings of the same shape - a full
     * page of forty-eight cards with a page after it - because the shape costs
     * one statement of its own: core's paging bar, which a listing that fits on
     * one page does not render at all. That one is asserted separately and by
     * name, rather than folded into a tolerance that would hide a real slope.
     */
    public function test_a_page_costs_the_same_statements_over_forty_nine_courses_and_over_two_hundred(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $single = $generator->create_category();
        for ($i = 0; $i < 2; $i++) {
            $generator->create_course(['category' => $single->id]);
        }
        $medium = $generator->create_category();
        for ($i = 0; $i < 49; $i++) {
            $generator->create_course(['category' => $medium->id]);
        }
        $large = $generator->create_category();
        for ($i = 0; $i < 200; $i++) {
            $generator->create_course(['category' => $large->id]);
        }
        $this->become($generator->create_user());

        /* One page object per call, which is what the framework gives the function:
           call_external_function() builds a fresh moodle_page for every call, and a page
           whose context walks from one category to another is a state no request has. */
        $resolved = [];
        foreach ([$single, $medium, $large] as $index => $category) {
            $this->newpage();
            $resolved[$index] = \core_course_category::get((int) $category->id);
            rows::fetch($resolved[$index], []);
        }

        $this->newpage();
        $singlereads = $this->reads(function () use ($resolved) {
            $page = rows::fetch($resolved[0], []);
            $this->assertSame(2, $page['total']);
            $this->assertFalse($page['hasmore'], 'The one-page listing, which renders no pager.');
        });
        $this->newpage();
        $mediumreads = $this->reads(function () use ($resolved) {
            $page = rows::fetch($resolved[1], []);
            $this->assertSame(49, $page['total']);
            $this->assertTrue($page['hasmore']);
        });
        $this->newpage();
        $largereads = $this->reads(function () use ($resolved) {
            $page = rows::fetch($resolved[2], []);
            $this->assertSame(200, $page['total']);
            $this->assertTrue($page['hasmore']);
        });

        $this->assertSame(
            $mediumreads,
            $largereads,
            "{$mediumreads} statements over forty-nine courses against {$largereads} over two hundred."
        );
        $this->assertSame(
            1,
            $largereads - $singlereads,
            "A one-page listing cost {$singlereads} statements and a paged one {$largereads}: the "
                . 'difference must be core\'s paging bar alone.'
        );
    }
}
