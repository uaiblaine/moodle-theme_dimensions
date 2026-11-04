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
 * Theme Dimensions - Tests of the category hero's export
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use core_course_category;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\category\branding;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\fixture;
use theme_dimensions\local\category\population;
use theme_dimensions\local\landing\links;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../local/category/fixture.php');

/**
 * Tests of the category hero's export: what it says about the category, the viewer and the request.
 *
 * The tabs are core's secondary navigation and are asserted on the rendered page in
 * core_renderer_test; export() tells the page it has none, and the two tests about tabs
 * set up a category page of their own.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(category_hero::class)]
final class category_hero_test extends \advanced_testcase {
    use fixture;

    /** @var string A valid 1x1 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /**
     * Store a crest in one of a category's badge areas, as the editor's file manager would.
     *
     * @param int $categoryid The category.
     * @param string $area One of branding::AREA_BADGES.
     * @param string $filename The file name, which is also the alternative text the hero draws.
     * @return void
     */
    private static function store_badge(int $categoryid, string $area, string $filename): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \core\context\coursecat::instance($categoryid)->id,
            'component' => branding::COMPONENT,
            'filearea' => $area,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
        ], base64_decode(self::PNG));
    }

    /**
     * A hero over a resolved page of the category, for the current user.
     *
     * @param int $categoryid The category.
     * @param array $options Criteria options.
     * @param bool $folded Whether the hero is folded.
     * @param string $description The formatted description.
     * @return category_hero
     */
    private function hero(int $categoryid, array $options = [], bool $folded = false, string $description = ''): category_hero {
        $criteria = new criteria($categoryid, $options);
        $result = population::resolve($criteria);
        $category = core_course_category::get($categoryid, MUST_EXIST, true);
        return new category_hero($category, $result, $criteria, $folded, $description);
    }

    /**
     * Export a hero on a page without a secondary navigation.
     *
     * @param category_hero $hero The hero.
     * @return \stdClass The context.
     */
    private function export(category_hero $hero): \stdClass {
        global $PAGE;
        $PAGE->set_context(\core\context\coursecat::instance($hero->category_id()));
        $PAGE->set_url('/course/index.php', ['categoryid' => $hero->category_id()]);
        $PAGE->set_secondary_navigation(false);
        return $hero->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * The export names the category in the plain spelling, prints the tagline, and counts post-filter.
     */
    public function test_the_export_names_the_category_the_tagline_and_the_counts(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category(['name' => 'Brigada & Resgate']);
        $categoryid = (int) $category->id;
        $one = $generator->create_course(['category' => $categoryid]);
        $generator->create_course(['category' => $categoryid]);
        $hidden = $generator->create_course(['category' => $categoryid, 'visible' => 0]);
        $viewer = $generator->create_user();
        $generator->enrol_user($viewer->id, $one->id);
        $insider = $generator->create_user();
        $generator->enrol_user($insider->id, $hidden->id);
        $generator->enrol_user($insider->id, $one->id);
        $this->setAdminUser();
        branding::save($categoryid, (object) ['tagline' => 'Formação continuada']);

        $this->become($viewer);
        $context = $this->export($this->hero($categoryid, [], false, '<p>Descrição</p>'));
        $this->assertSame('Brigada & Resgate', $context->title, 'Plain: the template escapes.');
        $this->assertSame('Formação continuada', $context->tagline);
        $this->assertTrue($context->hastagline);
        $this->assertSame('<p>Descrição</p>', $context->descriptionhtml);
        $this->assertTrue($context->hasdescription);
        $this->assertSame([2, 1], array_column($context->stats, 'value'), 'Two visible courses; enrolled in one.');
        $this->assertSame(get_string('categoryhero_stat_enrolled', 'theme_dimensions'), $context->stats[1]['label']);
        $this->assertFalse($context->compact);
        $this->assertFalse($context->haspencil);

        // The administrator sees the hidden course counted, and as hidden.
        $this->setAdminUser();
        self::forget();
        $context = $this->export($this->hero($categoryid));
        $this->assertSame([3, 1], array_column($context->stats, 'value'));
        $hiddenlabel = get_string('categoryhero_stat_hidden', 'theme_dimensions');
        $this->assertSame($hiddenlabel, $context->stats[1]['label']);
        $this->assertTrue($context->hastagline);

        // A student enrolled in the hidden course and in one visible course is not admitted to the
        // hidden one, as in core: the enrolment does not count it, and "hidden from students" is a
        // staff number, never a line for that student.
        $this->become($insider);
        $context = $this->export($this->hero($categoryid));
        $this->assertSame(
            [2, 1],
            array_column($context->stats, 'value'),
            'Two visible courses; the hidden one is neither listed nor counted.'
        );
        $this->assertNotContains($hiddenlabel, array_column($context->stats, 'label'));
        $this->assertSame(get_string('categoryhero_stat_enrolled', 'theme_dimensions'), $context->stats[1]['label']);
    }

    /**
     * A teacher of a hidden course has it listed and counted, and is still not told how many courses are hidden.
     *
     * The population admits a hidden course on the capability in the COURSE's context, which a
     * teacher holds in their own course; the hidden count is a number about the category, so the
     * hero asks for the capability in the CATEGORY's context. The control is the total: it counts
     * the hidden course, so the population did hand the hero a hidden count to withhold.
     */
    public function test_a_teacher_of_a_hidden_course_is_not_told_the_hidden_count(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $categoryid = (int) $generator->create_category()->id;
        $generator->create_course(['category' => $categoryid]);
        $hidden = $generator->create_course(['category' => $categoryid, 'visible' => 0]);
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $hidden->id, 'editingteacher');
        $categorycontext = \core\context\coursecat::instance($categoryid);
        $this->assertTrue(has_capability('moodle/course:viewhiddencourses', \core\context\course::instance($hidden->id), $teacher));
        $this->assertFalse(has_capability('moodle/course:viewhiddencourses', $categorycontext, $teacher));

        $this->become($teacher);
        $result = population::resolve(new criteria($categoryid));
        $this->assertContains((int) $hidden->id, $result->ids(), 'The control: the hidden course is the teacher\'s to see.');
        $this->assertSame(1, $result->hidden, 'And the population counted it as hidden.');

        self::forget();
        $context = $this->export($this->hero($categoryid));
        $this->assertSame(2, $context->stats[0]['value'], 'The total counts the hidden course.');
        $this->assertNotContains(
            get_string('categoryhero_stat_hidden', 'theme_dimensions'),
            array_column($context->stats, 'label'),
            'The hidden count is the category\'s staff number, not the teacher\'s.'
        );
    }

    /**
     * The hero draws the category's own crests, with the file name as their alternative text.
     *
     * One rule in three parts: the crests are the category's and inherit nothing (a parent with
     * crests leaves a child without any showing none), they arrive in the editor's order with a
     * gap closed, and a folded band drops them with the tagline and the counts.
     */
    public function test_the_hero_draws_the_categorys_own_crests(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $parent = (int) $generator->create_category(['name' => 'Organizações'])->id;
        $child = (int) $generator->create_category(['name' => 'Brigada', 'parent' => $parent])->id;
        $generator->create_course(['category' => $child]);
        $this->setAdminUser();

        $context = $this->export($this->hero($child));
        $this->assertSame([], $context->badges, 'A category nobody gave a crest shows none.');
        $this->assertFalse($context->hasbadges);

        // The parent is branded and the child is not: nothing is inherited.
        self::store_badge($parent, branding::AREA_BADGES[0], 'crest-alpha.png');
        branding::forget_all();
        $this->assertSame([], $this->export($this->hero($child))->badges, 'And nothing comes down from the parent.');

        /* Now the child's own, in the first and third slots: the order is the editor's and the
           gap closes, and the alternative text is the file name read as words. */
        self::store_badge($child, branding::AREA_BADGES[2], 'sigma.svg');
        self::store_badge($child, branding::AREA_BADGES[0], 'crest_alpha.png');
        branding::forget_all();
        $context = $this->export($this->hero($child));

        $this->assertTrue($context->hasbadges);
        $this->assertSame(['crest alpha', 'sigma'], array_column($context->badges, 'alt'));
        $this->assertStringContainsString('/categorybadge1/' . $child . '/', $context->badges[0]['url']);
        $this->assertStringContainsString('/crest_alpha.png', $context->badges[0]['url']);
        $this->assertStringContainsString('/categorybadge3/' . $child . '/', $context->badges[1]['url']);
        $this->assertNotEmpty($context->badgeslabel, 'The list is labelled, because its items are images.');

        /* Folded: the band drops the tagline and the counts and KEEPS the crests, the same
           files with the same alternative text - the stylesheet alone makes them small. */
        $folded = $this->export($this->hero($child, [], true));
        $this->assertTrue($folded->compact, 'Precondition: this is the folded band.');
        $this->assertTrue($folded->hasbadges, 'A folded hero keeps its crests.');
        $this->assertSame(['crest alpha', 'sigma'], array_column($folded->badges, 'alt'));
        $this->assertSame(array_column($context->badges, 'url'), array_column($folded->badges, 'url'));
        $this->assertNotEmpty($folded->badgeslabel);
    }

    /**
     * The compact LAYOUT keeps the crests too, and the markup puts them in the band beside the title.
     *
     * The fold is a reader's choice and the compact layout a manager's; both make the band compact,
     * and neither drops the crests any more. Rendered through the template, so a list that the
     * context carried and the markup no longer drew in the compact band would fail here.
     */
    public function test_the_compact_layout_draws_the_crests_beside_the_title(): void {
        global $PAGE;
        $this->resetAfterTest();
        $categoryid = (int) self::getDataGenerator()->create_category(['name' => 'Brigada'])->id;
        self::getDataGenerator()->create_course(['category' => $categoryid]);
        $this->setAdminUser();
        branding::save($categoryid, (object) [
            'layout' => branding::LAYOUT_COMPACT,
            'bgcolour' => '#2f3338',
            'inkcolour' => '#ffffff',
            'owncolours' => 1,
        ]);
        self::store_badge($categoryid, branding::AREA_BADGES[1], 'crest-beta.png');
        branding::forget_all();

        $hero = $this->hero($categoryid);
        $context = $this->export($hero);
        $this->assertTrue($context->compact, 'Precondition: the compact layout.');
        $this->assertSame(['crest beta'], array_column($context->badges, 'alt'));

        $html = $PAGE->get_renderer('core')->render_from_template('theme_dimensions/category_hero', $context);
        $this->assertStringContainsString('dim-hero--compact', $html);
        $this->assertMatchesRegularExpression(
            '~<ul class="dim-hero__badges"[^>]*data-region="dim-hero-badges"[^>]*>\s*<li class="dim-hero__badge"><img src="[^"]+"'
                . ' alt="crest beta"~',
            $html
        );
    }

    /**
     * The pencil is offered to a manager of the category and to nobody else.
     */
    public function test_the_pencil_is_offered_to_a_manager_only(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $categoryid = (int) $generator->create_category()->id;
        $generator->create_course(['category' => $categoryid]);
        $context = \core\context\coursecat::instance($categoryid);

        $this->become($generator->create_user());
        $this->assertFalse($this->export($this->hero($categoryid))->haspencil);

        $this->become($this->user_with('moodle/category:manage', $context));
        $exported = $this->export($this->hero($categoryid));
        $this->assertTrue($exported->haspencil);
        $this->assertStringContainsString('/dimensions/categoryhero.php?id=' . $categoryid, $exported->pencil['url']);
        $this->assertSame(get_string('categoryhero_pencil', 'theme_dimensions'), $exported->pencil['label']);
    }

    /**
     * The layout and the overlay become classes; an image layout without an image is the solid band.
     */
    public function test_the_layout_and_the_overlay_become_classes(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $categoryid = (int) $generator->create_category()->id;
        $generator->create_course(['category' => $categoryid]);
        $this->setAdminUser();

        $context = $this->export($this->hero($categoryid));
        $this->assertSame('dim-hero--solid dim-hero--notabs', $context->classes);
        $this->assertFalse($context->hasimage);
        $this->assertTrue($context->hasfold);

        // An image layout with nothing to paint: the solid band, in the chosen colour.
        branding::save($categoryid, (object) [
            'layout' => branding::LAYOUT_IMAGE,
            'bgcolour' => '#12484f',
            'inkcolour' => '#ffffff',
            'overlay' => 60,
        ]);
        $context = $this->export($this->hero($categoryid));
        $this->assertSame('dim-hero--solid dim-hero--notabs', $context->classes);
        $this->assertFalse($context->hasimage);

        // With the category's own image: the photograph behind the overlay, through the theme's route.
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \core\context\coursecat::instance($categoryid)->id,
            'component' => branding::COMPONENT,
            'filearea' => branding::AREA_HERO,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'hero.png',
        ], base64_decode(self::PNG));
        branding::forget_all();
        $context = $this->export($this->hero($categoryid));
        /* The classes name the layout and nothing about the colour: the overlay is the alpha of
           the gradient stops in the style attribute (see
           test_the_image_layout_paints_translucent_stops_over_an_opaque_strip). */
        $this->assertSame('dim-hero--image dim-hero--notabs', $context->classes);
        $this->assertTrue($context->hasimage);
        $expectedpath = '/categoryhero/' . $categoryid . '/' . $file->get_contenthash() . '/hero.png';
        $this->assertStringContainsString($expectedpath, $context->imageurl);

        // Compact: the title row alone, no fold control, no counts.
        branding::save($categoryid, (object) [
            'layout' => branding::LAYOUT_COMPACT,
            'bgcolour' => '#2f3338',
            'inkcolour' => '#ffffff',
        ]);
        $context = $this->export($this->hero($categoryid));
        $this->assertSame('dim-hero--compact dim-hero--notabs', $context->classes);
        $this->assertTrue($context->compact);
        $this->assertFalse($context->hasfold);
        $this->assertFalse($context->hasimage);
    }

    /**
     * The fold is remembered for a logged-in person, not for the guest, and the folded hero is compact with an expand control.
     */
    public function test_the_fold_is_remembered_for_a_logged_in_person_only(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $categoryid = (int) $generator->create_category()->id;
        $generator->create_course(['category' => $categoryid]);
        $viewer = $generator->create_user();

        $this->become($viewer);
        $this->assertFalse(category_hero::fold(null), 'Open until somebody folds it.');
        $this->assertTrue(category_hero::fold(true));
        $this->assertSame('1', get_user_preferences(category_hero::PREFERENCE_FOLDED, null, (int) $viewer->id));
        $this->assertTrue(category_hero::fold(null), 'Remembered.');
        $this->assertFalse(category_hero::fold(false));
        $this->assertSame('0', get_user_preferences(category_hero::PREFERENCE_FOLDED, null, (int) $viewer->id));

        $this->become(guest_user());
        $this->assertTrue(category_hero::fold(true), 'For this page.');
        $this->assertFalse(category_hero::fold(null), 'And not remembered.');

        // Folded, the hero is compact and offers to expand, carrying the request's filters.
        $this->become($viewer);
        $context = $this->export($this->hero($categoryid, ['search' => 'Curso'], true));
        $this->assertTrue($context->compact);
        $this->assertStringContainsString('dim-hero--folded', $context->classes);
        $this->assertStringContainsString('dim-hero--compact', $context->classes);
        $this->assertTrue($context->hasfold);
        $this->assertSame(0, $context->fold['value']);
        $this->assertSame(get_string('categoryhero_unfold', 'theme_dimensions'), $context->fold['label']);
        $this->assertSame(category_hero::PARAM_FOLD, $context->fold['name']);
        $this->assertContains(['name' => 'categoryid', 'value' => $categoryid, 'region' => ''], $context->fold['params']);
        $this->assertContains(
            ['name' => criteria::PARAM_SEARCH, 'value' => 'Curso', 'region' => ''],
            $context->fold['params']
        );
    }

    /**
     * Folding the hero keeps the open panel: the fold form carries it, each field under its own region.
     *
     * The form is a GET to the page's own address, so what it does not carry the reload drops:
     * without the panel's two fields, folding the hero would close the panel being read. The
     * panel module rewrites the panel into the address after this form was drawn and finds the
     * two fields by their regions to keep them in step. A hero with no panel open is the control:
     * no field for a panel that is not there.
     */
    public function test_the_fold_form_keeps_the_open_panel(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $categoryid = (int) $generator->create_category()->id;
        $childid = (int) $generator->create_category(['parent' => $categoryid])->id;
        $generator->create_course(['category' => $childid]);
        $this->become($generator->create_user());

        $context = $this->export($this->hero($categoryid, [
            'withposters' => true,
            'open' => $childid,
            'psort' => criteria::SORT_NAME,
        ]));
        $this->assertTrue($context->hasfold, 'Precondition: the fold control is drawn.');
        $this->assertContains(
            ['name' => criteria::PARAM_OPEN, 'value' => $childid, 'region' => 'dim-open-field'],
            $context->fold['params'],
            'The open panel travels with the fold.'
        );
        $this->assertContains(
            ['name' => criteria::PARAM_PSORT, 'value' => criteria::SORT_NAME, 'region' => 'dim-psort-field'],
            $context->fold['params'],
            'And its order.'
        );

        global $PAGE;
        $html = $PAGE->get_renderer('core')->render_from_template('theme_dimensions/category_hero', $context);
        $this->assertMatchesRegularExpression(
            '~<form[^>]*data-region="dim-hero-fold"[^>]*>.*?<input type="hidden" name="open" value="' . $childid
                . '" data-region="dim-open-field">.*?<input type="hidden" name="psort" value="name" data-region="dim-psort-field">'
                . '.*?</form>~s',
            $html,
            'The rendered fold form carries both, inside the form the panel module finds by its region.'
        );

        $closed = $this->export($this->hero($categoryid, ['withposters' => true]));
        foreach ($closed->fold['params'] as $field) {
            $this->assertNotContains(
                $field['name'],
                [criteria::PARAM_OPEN, criteria::PARAM_PSORT],
                'The control: no panel, no field.'
            );
        }
    }

    /**
     * The tabs are the page's secondary navigation: on the category page a learner gets the category tab.
     */
    public function test_the_tabs_come_from_the_secondary_navigation(): void {
        global $PAGE;
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $categoryid = (int) $generator->create_category()->id;
        $generator->create_course(['category' => $categoryid]);
        $this->become($generator->create_user());
        $hero = $this->hero($categoryid);

        $PAGE->set_category_by_id($categoryid);
        $PAGE->set_url('/course/index.php', ['categoryid' => $categoryid]);
        $PAGE->set_pagetype('course-index-category');
        $context = $hero->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($context->hastabs);
        $this->assertStringNotContainsString('dim-hero--notabs', $context->classes);
        $texts = [];
        foreach ($context->tabs['nodecollection']->children as $node) {
            $texts[] = (string) $node->text;
        }
        $this->assertContains(get_string('category'), $texts);
        $this->assertNotContains(get_string('settings'), $texts, 'A learner has no settings tab.');
    }

    /**
     * The registry hands the armed hero to the header and forgets it on reset.
     */
    public function test_the_registry_arms_and_resets(): void {
        $this->resetAfterTest();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $this->setAdminUser();

        $this->assertNull(category_hero::current());
        $hero = $this->hero($categoryid);
        category_hero::arm($hero);
        $this->assertSame($hero, category_hero::current());
        $this->assertSame($categoryid, category_hero::current()->category_id());
        category_hero::reset();
        $this->assertNull(category_hero::current());
    }

    /**
     * A PUBLIC category holding one public course, one listed course and one enrolled viewer.
     *
     * @return array The category id and the enrolled viewer.
     */
    private function public_category(): array {
        global $CFG;
        $CFG->forcelogin = 1;
        $CFG->routerconfigured = false;
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $adminid = (int) get_admin()->id;
        $categoryid = (int) $generator->create_category(['name' => 'Brigada'])->id;
        $public = $generator->create_course(['category' => $categoryid, 'fullname' => 'Curso Publico']);
        $generator->create_course(['category' => $categoryid, 'fullname' => 'Curso Interno']);
        $viewer = $generator->create_user();
        $generator->enrol_user($viewer->id, $public->id);
        \local_unlistedcourses\discoverability::set_state(
            (int) $public->id,
            \local_unlistedcourses\discoverability::STATE_PUBLIC,
            $adminid
        );
        \local_unlistedcourses\category_discoverability::set_state(
            $categoryid,
            \local_unlistedcourses\category_discoverability::STATE_PUBLIC,
            $adminid
        );
        self::forget();
        return [$categoryid, $viewer];
    }

    /**
     * A hero built for a visitor posts its fold form to the public page and counts nothing personal.
     *
     * The enrolled viewer's own hero of the same category, in the same run, is the control for
     * both halves: it posts to core's /course/index.php with the categoryid the showcase reads,
     * and it carries the enrolment line the visitor's hero has no viewer to count.
     *
     * @return void
     */
    public function test_an_anonymous_hero_targets_the_public_page_with_the_total_alone(): void {
        $this->resetAfterTest();
        [$categoryid, $viewer] = $this->public_category();
        $category = core_course_category::get($categoryid, MUST_EXIST, true);

        // The visitor first: the anonymous population may only be resolved in a visitor session.
        $this->become(null);
        $criteria = new criteria($categoryid, ['anonymous' => true]);
        $result = population::resolve($criteria);
        $context = $this->export(new category_hero($category, $result, $criteria, false, '', false));

        $this->assertTrue($context->hasfold);
        $this->assertStringEndsWith('/theme/dimensions/category.php', $context->fold['action']);
        $this->assertSame([['name' => 'id', 'value' => $categoryid, 'region' => '']], $context->fold['params']);
        $this->assertFalse($context->haspencil, 'User 0 holds no capability.');
        $this->assertSame([1], array_column($context->stats, 'value'), 'One public course, and no viewer to count.');
        $this->assertCount(1, $context->stats);

        // Control: the enrolled viewer's own hero of the same category.
        $this->become($viewer);
        $criteria = new criteria($categoryid);
        $result = population::resolve($criteria);
        $context = $this->export(new category_hero($category, $result, $criteria));
        $this->assertStringEndsWith('/course/index.php', $context->fold['action']);
        $this->assertContains(['name' => 'categoryid', 'value' => $categoryid, 'region' => ''], $context->fold['params']);
        $this->assertSame([2, 1], array_column($context->stats, 'value'), 'Both courses, and their own enrolment.');

        // Control: a manager of the same category does get the pencil.
        $this->become($this->user_with('moodle/category:manage', \core\context\coursecat::instance($categoryid)));
        $criteria = new criteria($categoryid);
        $context = $this->export(new category_hero($category, population::resolve($criteria), $criteria));
        $this->assertTrue($context->haspencil);
    }

    /**
     * A visitor's hero offers the public calendar while the calendar page would serve that visitor: the switch on and
     * the Gregorian calendar; a logged-in reader's hero never does.
     *
     * @return void
     */
    public function test_a_visitor_is_offered_the_public_calendar(): void {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/tests/calendartype_test_example.php');
        $this->resetAfterTest();
        [$categoryid, $viewer] = $this->public_category();
        set_config(\theme_dimensions\local\calendar\visitor::SETTING, '1', 'theme_dimensions');
        $category = core_course_category::get($categoryid, MUST_EXIST, true);

        $this->become(null);
        $criteria = new criteria($categoryid, ['anonymous' => true]);
        $result = population::resolve($criteria);
        $visitor = fn(): \stdClass => $this->export(new category_hero($category, $result, $criteria, false, '', false));

        $context = $visitor();
        $this->assertTrue($context->hascalendar);
        $this->assertSame(\theme_dimensions\local\calendar\page::url()->out(false), $context->calendar['url']);
        $this->assertSame(get_string('calendar_visitorlink', 'theme_dimensions'), $context->calendar['label']);

        set_config(\theme_dimensions\local\calendar\visitor::SETTING, '0', 'theme_dimensions');
        $this->assertFalse($visitor()->hascalendar, 'The public calendar off.');
        set_config(\theme_dimensions\local\calendar\visitor::SETTING, '1', 'theme_dimensions');
        $this->assertTrue($visitor()->hascalendar, 'Control: on again.');

        $CFG->calendartype = 'test_example';
        $this->assertFalse($visitor()->hascalendar, 'A calendar type the page refuses.');
        $CFG->calendartype = 'gregorian';
        $this->assertTrue($visitor()->hascalendar, 'Control: the Gregorian again.');

        $this->become($viewer);
        $criteria = new criteria($categoryid);
        $context = $this->export(new category_hero($category, population::resolve($criteria), $criteria));
        $this->assertFalse($context->hascalendar, 'A logged-in reader has the drawer.');
        $this->assertNull($context->calendar);
    }

    /**
     * The trail above the title names the category's ancestors, and a visitor is shown only the public ones.
     *
     * Built from the category's path rather than from core's breadcrumb ({@see category_hero::trail()}).
     * A listed ancestor of a public category is neither linked nor named to a visitor, and the
     * course index a logged-in viewer gets as the root is no page a visitor has.
     *
     * @return void
     */
    public function test_the_trail_names_the_ancestors_and_a_visitor_sees_only_the_public_ones(): void {
        global $CFG, $PAGE;

        $this->resetAfterTest();
        $CFG->forcelogin = 1;
        $CFG->routerconfigured = false;
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $adminid = (int) get_admin()->id;
        $public = \local_unlistedcourses\category_discoverability::STATE_PUBLIC;
        $rootid = (int) $generator->create_category(['name' => 'Organizações'])->id;
        $listedid = (int) $generator->create_category(['name' => 'Proteção & Brigada', 'parent' => $rootid])->id;
        $categoryid = (int) $generator->create_category(['name' => 'Alfa', 'parent' => $listedid])->id;
        $course = $generator->create_course(['category' => $categoryid]);
        $viewer = $generator->create_user();
        $generator->enrol_user($viewer->id, $course->id);
        $topid = (int) $generator->create_category(['name' => 'Topo'])->id;
        $topcourse = $generator->create_course(['category' => $topid]);
        foreach ([$course, $topcourse] as $c) {
            \local_unlistedcourses\discoverability::set_state(
                (int) $c->id,
                \local_unlistedcourses\discoverability::STATE_PUBLIC,
                $adminid
            );
        }
        foreach ([$rootid, $categoryid, $topid] as $id) {
            \local_unlistedcourses\category_discoverability::set_state($id, $public, $adminid);
        }
        self::forget();

        // The logged-in viewer: the course index, then every ancestor root first, never the category itself.
        $this->become($viewer);
        $context = $this->export($this->hero($categoryid));
        $this->assertTrue($context->hastrail);
        $this->assertSame(get_string('breadcrumb', 'access'), $context->traillabel);
        $this->assertSame(
            [get_string('courses'), 'Organizações', 'Proteção & Brigada'],
            array_column($context->trail, 'name'),
            'Root first, in the plain spelling; the template escapes.'
        );
        $this->assertStringEndsWith('/course/index.php', $context->trail[0]['url']);
        $this->assertStringEndsWith('/course/index.php?categoryid=' . $rootid, $context->trail[1]['url']);
        $this->assertStringEndsWith('/course/index.php?categoryid=' . $listedid, $context->trail[2]['url']);

        // The visitor: the public root, linked to its own public page; the listed ancestor is not
        // even named; and no course index.
        $this->become(null);
        $category = core_course_category::get($categoryid, MUST_EXIST, true);
        $criteria = new criteria($categoryid, ['anonymous' => true]);
        $result = population::resolve($criteria);
        $context = $this->export(new category_hero($category, $result, $criteria, false, '', false));
        $this->assertTrue($context->hastrail);
        $this->assertSame(['Organizações'], array_column($context->trail, 'name'), 'Only a PUBLIC ancestor is named.');
        $this->assertSame(links::category_page($rootid)->out(false), $context->trail[0]['url']);

        // A public category at the top level gives the visitor nothing to climb to: no trail at all.
        // On a fresh page: the one above already holds the other category's context.
        $PAGE = new \moodle_page();
        $criteria = new criteria($topid, ['anonymous' => true]);
        $result = population::resolve($criteria);
        $top = core_course_category::get($topid, MUST_EXIST, true);
        $context = $this->export(new category_hero($top, $result, $criteria, false, '', false));
        $this->assertFalse($context->hastrail);
        $this->assertSame([], $context->trail);
    }

    /**
     * The anonymous shell draws no tab strip, and the withtabs flag is what drops it.
     *
     * The page really does carry a secondary navigation here - the hero built with the flag on
     * exports its tabs - so the empty half cannot be an artefact of a page that has none.
     *
     * @return void
     */
    public function test_the_anonymous_hero_draws_no_tab_strip(): void {
        global $PAGE;
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $categoryid = (int) $generator->create_category()->id;
        $generator->create_course(['category' => $categoryid]);
        $this->become($generator->create_user());
        $criteria = new criteria($categoryid);
        $result = population::resolve($criteria);
        $category = core_course_category::get($categoryid, MUST_EXIST, true);

        $PAGE->set_category_by_id($categoryid);
        $PAGE->set_url('/course/index.php', ['categoryid' => $categoryid]);
        $PAGE->set_pagetype('course-index-category');
        $renderer = $PAGE->get_renderer('core');

        $withtabs = (new category_hero($category, $result, $criteria))->export_for_template($renderer);
        $this->assertTrue($withtabs->hastabs, 'Control: the page really has a secondary navigation.');
        $this->assertStringNotContainsString('dim-hero--notabs', $withtabs->classes);

        $notabs = (new category_hero($category, $result, $criteria, false, '', false))->export_for_template($renderer);
        $this->assertFalse($notabs->hastabs);
        $this->assertNull($notabs->tabs);
        $this->assertStringContainsString('dim-hero--notabs', $notabs->classes);
    }

    /**
     * The root's hero names the course index, brands itself from the defaults and offers no pencil.
     *
     * The top-level pseudo-category has no row, no context and no file area, so three things
     * the hero does for every other category are answered without one: the title (top()'s own
     * formatted name is the word "Top"), the branding (the defaults, read with no statement: a
     * get_record() on category 0 can only miss) and the pencil (the appearance editor brands a
     * real category). The trail is empty because the root is the course index, and the fold
     * form posts to /course/index.php with no category, the fold being a preference of the
     * person rather than of the category.
     *
     * The control is a real category of the same site, which carries its name, its trail and,
     * for this admin, its pencil.
     */
    public function test_the_root_hero_names_the_course_index_and_offers_no_pencil(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $categoryid = (int) $generator->create_category(['name' => 'Brigada'])->id;
        $generator->create_category();
        $generator->create_course(['category' => $categoryid]);
        $this->setAdminUser();
        self::forget();

        $criteria = new criteria(0);
        $result = population::resolve($criteria);
        $PAGE->set_url('/course/index.php');
        $PAGE->set_secondary_navigation(false);
        $renderer = $PAGE->get_renderer('core');
        $hero = new category_hero(core_course_category::top(), $result, $criteria);

        $before = $DB->perf_get_reads();
        $branding = branding::for_category(0);
        $this->assertSame($DB->perf_get_reads(), $before, 'The root reads no branding row.');
        $this->assertSame(branding::DEFAULT_BG, $branding['bgcolour']);
        $this->assertSame(branding::DEFAULT_INK, $branding['inkcolour']);
        /* The image layout over no image of its own: the root hero paints the site-wide fallback
           image behind the default navy, and on a site with no fallback it falls back to the solid
           band asserted below. It is the one value of this payload that is not the default. */
        $this->assertSame(branding::LAYOUT_IMAGE, $branding['layout']);
        $this->assertNull($branding['image']);
        $this->assertSame(branding::CACHED_KEYS, array_keys($branding), 'The whole payload, as for any category.');

        $context = $hero->export_for_template($renderer);
        $this->assertSame(0, $context->categoryid);
        $this->assertSame(get_string('courses'), $context->title);
        $this->assertStringContainsString(
            'dim-hero--' . branding::LAYOUT_SOLID,
            $context->classes,
            'No fallback uploaded: the image layout falls back to the solid band, as it always did.'
        );
        $this->assertFalse($context->hasimage);
        $this->assertNotSame(get_string('top'), $context->title);
        $this->assertFalse($context->haspencil);
        $this->assertNull($context->pencil);
        $this->assertFalse($context->hastrail);
        $this->assertSame([], $context->trail);
        $this->assertTrue($context->hasstats, 'The counts panel is drawn at the root too.');
        $this->assertTrue($context->hasfold);
        $this->assertStringEndsWith('/course/index.php', $context->fold['action']);
        $this->assertSame([], $context->fold['params'], 'No category travels with the root\'s fold.');

        // The control: the same admin on a real category gets the name, the trail and the pencil.
        $below = $this->export($this->hero($categoryid));
        $this->assertSame('Brigada', $below->title);
        $this->assertTrue($below->haspencil);
        $this->assertTrue($below->hastrail);
    }

    /**
     * The anonymous root's hero is the site: its name, its summary, its fallback image, no pencil.
     *
     * The root has no row, no context and no file area of its own, so what it draws comes from
     * the site. The logged-in root is the control in the same test: the same defaults, and the
     * word "Courses" over the course index, which is the page that reader asked for.
     *
     * The pencil is asserted absent for an administrator, the only viewer for whom its absence
     * proves anything: a visitor holds no capability, so a visitor's hero has no pencil however
     * the guard is written.
     */
    public function test_the_anonymous_root_hero_is_the_site_and_offers_no_pencil(): void {
        global $CFG, $PAGE, $SITE, $DB;

        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        set_config(\theme_dimensions\local\category\publicaccess::SETTING_ROOT, '1', 'theme_dimensions');
        $public = $generator->create_category(['name' => 'Public branch']);
        $this->set_category_state((int) $public->id, \local_unlistedcourses\category_discoverability::STATE_PUBLIC);
        $generator->create_course(['category' => $public->id]);

        // The site-wide hero image, stored as the setting's file manager would store it.
        $syscontext = \core\context\system::instance();
        get_file_storage()->create_file_from_string([
            'contextid' => $syscontext->id,
            'component' => branding::COMPONENT,
            'filearea' => branding::AREA_FALLBACK,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'fallback.png',
        ], base64_decode(self::PNG));
        set_config(branding::SETTING_FALLBACK, '/fallback.png', 'theme_dimensions');
        \core\output\theme_config::load('dimensions');

        $SITE->fullname = 'Acme & Formação';
        $DB->update_record('course', (object) ['id' => $SITE->id, 'fullname' => $SITE->fullname]);

        $this->become(null);
        $criteria = new criteria(0, ['anonymous' => true]);
        $result = population::resolve($criteria);
        $this->assertTrue($result->anonymous, 'Precondition: this really is the anonymous shell.');

        $PAGE->set_context($syscontext);
        $PAGE->set_url('/theme/dimensions/category.php', ['id' => 0]);
        $PAGE->set_secondary_navigation(false);
        $renderer = $PAGE->get_renderer('core');
        $hero = new category_hero(
            core_course_category::top(),
            $result,
            $criteria,
            false,
            '<p>Uma frase sobre o site.</p>',
            false,
            $PAGE
        );
        $context = $hero->export_for_template($renderer);

        $this->assertSame('Acme & Formação', $context->title, 'The site\'s name, plain, and never "Top".');
        $this->assertNotSame(get_string('courses'), $context->title, 'Nor the name of a page a visitor has not got.');
        $this->assertTrue($context->hasimage, 'The site-wide fallback is the root\'s picture.');
        $this->assertStringContainsString('/categoryherofallback/', $context->imageurl);
        $this->assertStringContainsString('dim-hero--' . branding::LAYOUT_IMAGE, $context->classes);
        $this->assertTrue($context->hasdescription, 'The site summary is the root\'s description.');
        $this->assertStringContainsString('Uma frase sobre o site.', $context->descriptionhtml);
        $this->assertFalse($context->hastrail, 'The root IS the top: there is nothing above it to name.');
        $this->assertSame([], $context->trail);
        $this->assertFalse($context->haspencil, 'A visitor brands nothing.');
        $this->assertStringStartsWith($CFG->wwwroot, $context->imageurl, 'One host, and the site\'s own.');

        /* The control, for both halves: an administrator on the logged-in root, where the pencil
           would appear if the root had an editor at all, and where the title is the page's. */
        $this->setAdminUser();
        self::forget();
        $loggedin = population::resolve(new criteria(0));
        $this->assertFalse($loggedin->anonymous);
        $admincontext = (new category_hero(core_course_category::top(), $loggedin, new criteria(0), false, '', false, $PAGE))
            ->export_for_template($renderer);
        $this->assertSame(get_string('courses'), $admincontext->title, 'Control: the logged-in root names the page.');
        $this->assertFalse($admincontext->haspencil, 'And no viewer gets a pencil at the root.');
    }

    /**
     * The hero's one inline style attribute can only ever hold hex colours and rgba() values.
     *
     * Mustache's escaping does not protect a CSS context, so what keeps this attribute safe is
     * the vocabulary the exporter can produce: every value passes through branding::hex(),
     * branding::stops() or branding::rgba(), each of which parses to integer channels and
     * prints them back. The regex is asserted on a real export and on a payload poisoned the
     * way a direct write, a migration or a restore could poison one. Changes that must make it
     * fail: style() printing a stored value without parsing it.
     */
    public function test_the_hero_style_holds_nothing_but_sanitised_colours(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $categoryid = (int) $generator->create_category()->id;
        $generator->create_course(['category' => $categoryid]);
        $this->setAdminUser();
        branding::save($categoryid, (object) [
            'layout' => branding::LAYOUT_SOLID,
            'bgcolour' => '#12484f',
            'inkcolour' => '#ffffff',
        ]);

        $vocabulary = '/^(--dim-hero-[a-z-]+:\s*(#[0-9a-f]{6}|rgba\([0-9, .]+\));\s*)+$/';
        $context = $this->export($this->hero($categoryid));
        $this->assertMatchesRegularExpression($vocabulary, $context->style);
        // The stored colour really is what the band paints: mid is the colour, the head darker.
        $this->assertStringContainsString('--dim-hero-mid: #12484f;', $context->style);
        $this->assertStringContainsString('--dim-hero-from: #0f3b41;', $context->style);
        $this->assertStringContainsString('--dim-hero-to: #2e5e64;', $context->style);
        $this->assertStringContainsString('--dim-hero-strip: #12484f;', $context->style);
        $this->assertStringContainsString('--dim-hero-ink: #ffffff;', $context->style);
        $this->assertStringContainsString('--dim-hero-ink-muted: rgba(255, 255, 255, 0.86);', $context->style);
        // The ring is the INK and not white: a white focus ring on a pale band is invisible.
        $this->assertStringContainsString('--dim-hero-ring: #ffffff;', $context->style);

        // A payload nothing sanitised: every value falls back to the default, none reaches the string.
        $poisoned = [
            'layout' => branding::LAYOUT_SOLID,
            'bgcolour' => '#fff;background:url(https://evil.example/x)',
            'inkcolour' => 'red" onload="alert(1)',
            'overlay' => 78,
        ];
        $style = category_hero::style($poisoned, branding::LAYOUT_SOLID);
        $this->assertMatchesRegularExpression($vocabulary, $style);
        $this->assertStringNotContainsString('url(', $style);
        $this->assertStringNotContainsString('evil.example', $style);
        $this->assertStringNotContainsString('onload', $style);
        $this->assertStringNotContainsString('"', $style);
        $this->assertStringContainsString('--dim-hero-mid: ' . branding::DEFAULT_BG . ';', $style);
        $this->assertStringContainsString('--dim-hero-ink: ' . branding::DEFAULT_INK . ';', $style);
    }

    /**
     * Under a photograph the three stops turn translucent at the chosen overlay, and the strip does not.
     *
     * White tabs on a see-through strip vanish into the picture, so the strip keeps the opaque
     * colour while the three gradient stops carry the overlay's alpha.
     */
    public function test_the_image_layout_paints_translucent_stops_over_an_opaque_strip(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $categoryid = (int) $generator->create_category()->id;
        $generator->create_course(['category' => $categoryid]);
        $this->setAdminUser();
        branding::save($categoryid, (object) [
            'layout' => branding::LAYOUT_IMAGE,
            'bgcolour' => '#070093',
            'inkcolour' => '#ffffff',
            'overlay' => 60,
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => \core\context\coursecat::instance($categoryid)->id,
            'component' => branding::COMPONENT,
            'filearea' => branding::AREA_HERO,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'hero.png',
        ], base64_decode(self::PNG));
        branding::forget_all();

        $context = $this->export($this->hero($categoryid));
        $this->assertTrue($context->hasimage, 'The precondition: the image layout really is drawn.');
        $this->assertMatchesRegularExpression(
            '/^(--dim-hero-[a-z-]+:\s*(#[0-9a-f]{6}|rgba\([0-9, .]+\));\s*)+$/',
            $context->style
        );
        $this->assertStringContainsString('--dim-hero-from: rgba(6, 0, 121, 0.6);', $context->style);
        $this->assertStringContainsString('--dim-hero-mid: rgba(7, 0, 147, 0.6);', $context->style);
        $this->assertStringContainsString('--dim-hero-to: rgba(37, 31, 160, 0.6);', $context->style);
        $this->assertStringContainsString('--dim-hero-strip: #070093;', $context->style);
        $this->assertStringContainsString('--dim-hero-ink-muted: rgba(255, 255, 255, 0.92);', $context->style);

        // The overlay a manager chose reaches the values: 90% is a different string, not the same one.
        branding::save($categoryid, (object) [
            'layout' => branding::LAYOUT_IMAGE,
            'bgcolour' => '#070093',
            'inkcolour' => '#ffffff',
            'overlay' => 90,
        ]);
        $stronger = $this->export($this->hero($categoryid));
        $this->assertStringContainsString('--dim-hero-mid: rgba(7, 0, 147, 0.9);', $stronger->style);

        /* An image layout with no image to paint falls back to the solid band, and the stops go
           opaque with it: the layout actually drawn decides, not the stored one. Asserted on
           style() rather than on a second export: exporting another category would move $PAGE's
           context from one category to another, which moodle_page::set_context() reports with a
           debugging() call, and an unexpected debugging() call fails the test. */
        $stored = [
            'layout' => branding::LAYOUT_IMAGE,
            'bgcolour' => '#070093',
            'inkcolour' => '#ffffff',
            'overlay' => 60,
        ];
        $nopicture = category_hero::style($stored, branding::LAYOUT_SOLID);
        $this->assertStringContainsString('--dim-hero-mid: #070093;', $nopicture);
        $this->assertStringNotContainsString('rgba(7, 0, 147', $nopicture);
        $this->assertStringContainsString('--dim-hero-ink-muted: rgba(255, 255, 255, 0.86);', $nopicture);
    }

    /**
     * At the transparent overlay the three stops are fully transparent and the strip is not.
     *
     * The gradient's three stops go to alpha 0, so the browser paints nothing over the picture,
     * while --dim-hero-strip keeps the opaque mid colour, because the tab strip is drawn from it
     * and white tabs on a bare photograph would be unreadable. The alpha is printed as "0", never
     * as "0.": a trailing point is invalid CSS and the browser drops the whole declaration.
     */
    public function test_the_transparent_overlay_paints_nothing_over_the_photograph(): void {
        $stored = [
            'layout' => branding::LAYOUT_IMAGE,
            'bgcolour' => '#070093',
            'inkcolour' => '#ffffff',
            'overlay' => 0,
        ];
        $style = category_hero::style($stored, branding::LAYOUT_IMAGE);

        $this->assertMatchesRegularExpression(
            '/^(--dim-hero-[a-z-]+:\s*(#[0-9a-f]{6}|rgba\([0-9, .]+\));\s*)+$/',
            $style,
            'The closed vocabulary of the one inline style attribute still holds at overlay 0.'
        );
        $this->assertStringContainsString('--dim-hero-from: rgba(6, 0, 121, 0);', $style);
        $this->assertStringContainsString('--dim-hero-mid: rgba(7, 0, 147, 0);', $style);
        $this->assertStringContainsString('--dim-hero-to: rgba(37, 31, 160, 0);', $style);
        $this->assertStringNotContainsString(
            ', 0.);',
            $style,
            'A trailing point is invalid CSS and the browser drops the declaration entirely.'
        );
        $this->assertStringContainsString(
            '--dim-hero-strip: #070093;',
            $style,
            'The tab strip stays opaque, or the tabs vanish on a bare photograph.'
        );

        // The control: at the default overlay the same three stops are NOT transparent.
        $stored['overlay'] = branding::DEFAULT_OVERLAY;
        $opaque = category_hero::style($stored, branding::LAYOUT_IMAGE);
        $this->assertStringContainsString('--dim-hero-mid: rgba(7, 0, 147, 0.78);', $opaque);
    }
}
