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
 * Theme Dimensions - Tests for the tool_lp renderer and the learning plans page
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use block_dimensions\plan_cards;
use core_competency\plan;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\competency\evidencelist;
use theme_dimensions\local\competency\plancards;

/**
 * The learning plans page's and the evidence page's layers: the theme's renderer decorates the first paint, the
 * template renders from core's export alone (what tool_lp/planactions and tool_lp/user_evidence_actions give it on the
 * client), and both keep every hook of core's.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(tool_lp_renderer::class)]
#[CoversClass(plancards::class)]
#[CoversClass(evidencelist::class)]
final class tool_lp_renderer_test extends \advanced_testcase {
    /**
     * Put the page under this theme, before any renderer exists.
     *
     * @return void
     */
    private function under_theme(): void {
        global $PAGE;
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/admin/tool/lp/plans.php');
        $PAGE->force_theme('dimensions');
    }

    /**
     * Parse a render.
     *
     * @param string $html The markup.
     * @return \DOMXPath
     */
    private static function parse(string $html): \DOMXPath {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        return new \DOMXPath($document);
    }

    /**
     * A learner with an active plan and a completed one, the admin as the viewer.
     *
     * @return array [the learner, the active plan's id, the completed plan's id].
     */
    private function learner_with_plans(): array {
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $learner = $generator->create_user();
        $active = $lpg->create_plan(['userid' => $learner->id, 'name' => 'Kitchen plan', 'status' => plan::STATUS_ACTIVE]);
        $complete = $lpg->create_plan(['userid' => $learner->id, 'name' => 'Garden plan', 'status' => plan::STATUS_COMPLETE]);

        return [$learner, (int) $active->get('id'), (int) $complete->get('id')];
    }

    /**
     * The factory really does hand over this theme's renderer.
     *
     * The class name a theme override must carry is derived, not declared, so a file in the wrong directory or a
     * namespace off by one level fails by silently doing nothing at all: core's renderer answers and the page
     * merely lacks its cards.
     *
     * @return void
     */
    public function test_the_theme_renderer_is_the_one_the_factory_returns(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->under_theme();

        $this->assertInstanceOf(tool_lp_renderer::class, $PAGE->get_renderer('tool_lp'));
    }

    /**
     * The first paint carries one plan node per plan, each with the block's card, and the layout on the root.
     *
     * @return void
     */
    public function test_the_first_paint_carries_the_blocks_cards(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$learner, $activeid, $completeid] = $this->learner_with_plans();
        set_config(plancards::SETTING, plan_cards::LAYOUT_VERTICAL, 'theme_dimensions');
        $this->under_theme();

        $html = $PAGE->get_renderer('tool_lp')->render(new \tool_lp\output\plans_page((int) $learner->id));
        $xpath = self::parse($html);

        $root = "//div[@data-region='plans'][@data-dim-layout='vertical']" .
            "[@data-dim-itemclasses='" . plan_cards::item_classes(plan_cards::LAYOUT_VERTICAL) . "']";
        $this->assertSame(1, $xpath->query($root)->length, 'The root lost the first paint\'s layout.');
        $this->assertSame(2, $xpath->query("//*[@data-region='plan-node']")->length);
        foreach ([$activeid => 'active', $completeid => 'complete'] as $planid => $status) {
            $node = "//ul[contains(concat(' ', @class, ' '), ' block_dimensions ')]/li[@data-region='plan-node']" .
                "[@data-id='{$planid}'][@data-userid='{$learner->id}'][@data-dim-status='{$status}']" .
                "[contains(@class, 'dims-plan-item-vertical')]";
            $this->assertSame(1, $xpath->query($node)->length, "No node for plan {$planid}.");
            $card = $node . "//*[@data-region='dim-comp-extra'][@data-planid='{$planid}']" .
                "//article[contains(@class, 'plan-card')]//a[contains(@href, '/local/dimensions/view-plan.php?id={$planid}')]";
            $this->assertSame(1, $xpath->query($card)->length, "Plan {$planid} has no block card.");
            $this->assertSame(0, $xpath->query($node . "//article[contains(@class, 'dim-comp-plan-fallback')]")->length);
        }
        $complete = "//li[@data-id='{$activeid}']//ul[@class='planactions']/li/ul/li/a[@data-action='plan-complete']";
        $this->assertSame(1, $xpath->query($complete)->length, 'The admin may complete the active plan: no menu.');
    }

    /**
     * On the owner's own list the first paint says which plan is a favourite, which tag each carries, the tag filters
     * and the star's module, and core's due date and last change on every plan.
     *
     * @return void
     */
    public function test_the_first_paint_carries_favourites_tags_and_cores_dates(): void {
        global $PAGE, $USER;
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $owner = $generator->create_user();
        $template = $lpg->create_template(['shortname' => 'Kitchen']);
        $this->setAdminUser();
        \local_dimensions\helper::ensure_custom_fields_exist(\local_dimensions\helper::AREA_LP);
        \local_dimensions\customfield\lp_handler::create()->instance_form_save((object) [
            'id' => $template->get('id'),
            'customfield_' . \local_dimensions\constants::CFIELD_TAG1 => 3,
        ], true);
        $tagged = $lpg->create_plan([
            'userid' => $owner->id,
            'name' => 'A tagged plan',
            'templateid' => $template->get('id'),
            'status' => plan::STATUS_ACTIVE,
        ]);
        $plain = $lpg->create_plan(['userid' => $owner->id, 'name' => 'B plain plan', 'status' => plan::STATUS_ACTIVE]);
        set_config('enable_favourites', 1, 'block_dimensions');
        set_config('enable_plan_tag1_filter', 1, 'block_dimensions');
        $this->setUser($owner);
        $usercontext = \context_user::instance($owner->id);
        \core_favourites\service_factory::get_service_for_user_context($usercontext)
            ->create_favourite('block_dimensions', 'plan', (int) $tagged->get('id'), $usercontext);
        $this->assertSame((int) $owner->id, (int) $USER->id);
        $this->under_theme();

        $html = $PAGE->get_renderer('tool_lp')->render(new \tool_lp\output\plans_page((int) $owner->id));
        $xpath = self::parse($html);

        $root = $xpath->query("//div[@data-region='plans'][@data-dim-favourites='1']" .
            "[@data-dim-starmodule='" . plan_cards::STAR_MODULE . "']");
        $this->assertSame(1, $root->length, 'The root lost favourites or the star module.');
        $filters = json_decode($root->item(0)->getAttribute('data-dim-tagfilters'), true);
        $this->assertSame(['tag1'], array_column($filters, 'key'));
        $this->assertSame(plan_cards::tag_filters()[0]['label'], $filters[0]['label']);
        $node = "//li[@data-region='plan-node'][@data-id='%d'][@data-dim-fav='%s']";
        $this->assertSame(1, $xpath->query(sprintf($node, $tagged->get('id'), '1') . "[@data-dim-tag1='Edit me 3']")->length);
        $this->assertSame(1, $xpath->query(sprintf($node, $plain->get('id'), '0') . "[not(@data-dim-tag1)]")->length);
        foreach ([$tagged, $plain] as $plan) {
            $dated = "//li[@data-id='{$plan->get('id')}'][@data-dim-due='{$plan->get('duedate')}']" .
                "[@data-dim-modified='{$plan->get('timemodified')}']";
            $this->assertSame(1, $xpath->query($dated)->length, 'Core\'s dates are missing on a plan.');
        }
    }

    /**
     * Rendered from core's export alone, as the client re-renders it, the page has the fallback cards and every hook.
     *
     * @return void
     */
    public function test_cores_export_alone_renders_the_fallback_with_every_hook(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$learner, $activeid, $completeid] = $this->learner_with_plans();
        $this->under_theme();
        $output = $PAGE->get_renderer('core');
        $page = new \tool_lp\output\plans_page((int) $learner->id);

        $xpath = self::parse($output->render_from_template('tool_lp/plans_page', $page->export_for_template($output)));

        $this->assertSame(1, $xpath->query("//div[@data-region='plans'][contains(@class, 'dim-comp-plans')]")->length);
        $this->assertSame(0, $xpath->query("//*[@data-dim-layout]")->length, 'Core\'s context has no first paint.');
        $this->assertSame(0, $xpath->query("//article[contains(@class, 'plan-card')]")->length);
        foreach ([$activeid => 'Kitchen plan', $completeid => 'Garden plan'] as $planid => $name) {
            $node = "//li[@data-region='plan-node'][@data-id='{$planid}'][@data-userid='{$learner->id}']";
            $fallback = $node . "//*[@data-region='dim-comp-extra']//article[contains(@class, 'dim-comp-plan-fallback')]" .
                "//a[contains(@href, '/admin/tool/lp/plan.php?id={$planid}')][normalize-space(.)='{$name}']";
            $this->assertSame(1, $xpath->query($fallback)->length, "Plan {$planid} has no fallback card.");
            $menu = $node . "//ul[@class='planactions']/li/a[@href='#']/following-sibling::ul" .
                "[contains(@class, 'dropdown-menu')]/li[@class='dropdown-item']/a[@data-action='plan-delete']";
            $this->assertSame(1, $xpath->query($menu)->length, "Plan {$planid} lost core's menu structure.");
            $named = $node . "//ul[@class='planactions']//span[contains(@class, 'visually-hidden')]" .
                "[normalize-space(.)='Actions for {$name}']";
            $this->assertSame(1, $xpath->query($named)->length, "The menu of plan {$planid} is not named.");
            $this->assertSame(1, $xpath->query("//a[@data-action='plan-delete'][ancestor::li[@data-id='{$planid}']]" .
                "/ancestor::*[@data-region='plan-node']")->length, 'One plan node above each action, as _findPlanData needs.');
        }
        $this->assertSame(1, $xpath->query("//li[@data-id='{$activeid}']//a[@data-action='plan-complete']")->length);
        $this->assertSame(1, $xpath->query("//li[@data-id='{$completeid}']//a[@data-action='plan-reopen']")->length);
        // Core's plain edit link, under canbeedited: an active plan can be edited, a completed one cannot.
        $edit = "//li[@data-id='%d']//a[contains(@href, 'editplan.php?id=%d&userid={$learner->id}&return=plans')]";
        $this->assertSame(1, $xpath->query(sprintf($edit, $activeid, $activeid))->length);
        $this->assertSame(0, $xpath->query(sprintf($edit, $completeid, $completeid))->length);
        $this->assertSame(1, $xpath->query("//a[contains(@href, 'user_evidence_list.php?userid={$learner->id}')]")->length);
        $this->assertSame(1, $xpath->query("//*[@data-region='dim-comp-bar'][@hidden]")->length);
        $this->assertSame(1, $xpath->query("//*[@data-region='dim-comp-nomatch'][@hidden]")->length);
        // Core's own keys carry the sort's dates; the theme's favourites and tags are the first paint's only.
        $this->assertSame(2, $xpath->query("//li[@data-region='plan-node'][@data-dim-due][@data-dim-modified!='']")->length);
        $this->assertSame(0, $xpath->query("//*[@data-dim-fav or @data-dim-tag1 or @data-dim-tagfilters]")->length);
    }

    /**
     * The grid's host class is spelt in the template as a literal: it must be the block's.
     *
     * @return void
     */
    public function test_the_host_class_literal_is_the_blocks(): void {
        $template = file_get_contents(dirname(__DIR__, 2) . '/templates/tool_lp/plans_page.mustache');

        $this->assertSame(1, preg_match('~<ul class="([\w-]+) dim-comp-plans-grid~', $template, $matches));
        $this->assertSame(plan_cards::HOST_CLASS, $matches[1]);
    }

    /**
     * A learner with two pieces of prior-learning evidence a month apart, the newer one's name first in core's order
     * of names; the older one links a competency.
     *
     * @return array [the learner, the newer evidence's id, the older one's id].
     */
    private function learner_with_evidence(): array {
        global $DB;
        $generator = self::getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $learner = $generator->create_user();
        $newer = $lpg->create_user_evidence(['userid' => $learner->id, 'name' => 'A certificate']);
        $older = $lpg->create_user_evidence(['userid' => $learner->id, 'name' => 'B workshop']);
        $framework = $lpg->create_framework();
        $competency = $lpg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $lpg->create_user_evidence_competency(['userevidenceid' => $older->get('id'), 'competencyid' => $competency->get('id')]);
        // Core lists a linked competency through the user competency (user_evidence_competency::get_user_competencies...()).
        $lpg->create_user_competency(['userid' => $learner->id, 'competencyid' => $competency->get('id')]);
        $DB->set_field('competency_userevidence', 'timecreated', 1792065600, ['id' => $newer->get('id')]);
        $DB->set_field('competency_userevidence', 'timecreated', 1789473600, ['id' => $older->get('id')]);

        return [$learner, (int) $newer->get('id'), (int) $older->get('id')];
    }

    /**
     * The evidence page's first paint is newest first, headed by month, and says whether the platform's records may be
     * asked for; the owner may.
     *
     * @return void
     */
    public function test_the_evidence_first_paint_is_dated(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$learner, $newer, $older] = $this->learner_with_evidence();
        $this->setUser($learner);
        $this->setTimezone('UTC');
        $this->under_theme();

        $html = $PAGE->get_renderer('tool_lp')->render(new \tool_lp\output\user_evidence_list_page((int) $learner->id));
        $xpath = self::parse($html);

        $root = "//div[@data-region='user-evidence-list'][@data-dim-firstpaint='1'][@data-dim-platform='1']";
        $this->assertSame(1, $xpath->query($root)->length);
        $items = $xpath->query("//ol[@data-region='dim-comp-ev-list']/li");
        $order = [];
        foreach ($items as $item) {
            $order[] = $item->getAttribute('data-id') ?: trim($item->textContent);
        }
        $this->assertSame(['October 2026', (string) $newer, 'September 2026', (string) $older], $order);
    }

    /**
     * Rendered from core's export alone, as the client re-renders it, the evidence page has no first paint and every
     * hook of core's actions, with one evidence node above each action.
     *
     * @return void
     */
    public function test_the_evidence_page_from_cores_export_alone_keeps_every_hook(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$learner, $newer, $older] = $this->learner_with_evidence();
        $this->setUser($learner);
        $this->under_theme();
        $output = $PAGE->get_renderer('core');
        $page = new \tool_lp\output\user_evidence_list_page((int) $learner->id);

        $xpath = self::parse($output->render_from_template('tool_lp/user_evidence_list_page', $page->export_for_template($output)));

        $this->assertSame(1, $xpath->query("//div[@data-region='user-evidence-list'][contains(@class, 'dim-comp-ev')]")->length);
        $this->assertSame(0, $xpath->query("//*[@data-dim-firstpaint or @data-dim-platform]")->length);
        $this->assertSame(0, $xpath->query("//*[@data-region='dim-comp-ev-month']")->length, 'Months are the module\'s then.');
        foreach ([$newer, $older] as $id) {
            $node = "//li[@data-region='user-evidence-node'][@data-id='{$id}'][@data-userid='{$learner->id}'][@data-dim-time!='']";
            $this->assertSame(1, $xpath->query($node)->length);
            foreach (['user-evidence-delete', 'send-competencies-review'] as $action) {
                $above = "//ul[@class='user-evidence-actions']//a[@data-action='{$action}']" .
                    "[ancestor::li[@data-id='{$id}']]/ancestor::*[@data-region='user-evidence-node']";
                $message = "One evidence node above {$action}, as _findEvidenceData needs.";
                $this->assertSame(1, $xpath->query($above)->length, $message);
            }
        }
        $this->assertSame(1, $xpath->query("//li[@data-id='{$older}'][@data-dim-comps!='']")->length);
    }
}
