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
 * Theme Dimensions - Tests of the editor's inherited colours, card text and default organisation
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\form;

use local_unlistedcourses\category_access;
use local_unlistedcourses\category_discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\category\branding;
use theme_dimensions\local\category\criteria;

/**
 * Tests of the editor's three rows: own colours, the card text and the default organisation.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(categoryhero::class)]
#[CoversClass(branding::class)]
final class categoryhero_defaults_test extends \advanced_testcase {
    /**
     * A submission inside the vocabulary, with the box of own colours as given.
     *
     * @param int $categoryid The category.
     * @param int $owncolours The box.
     * @return array
     */
    private static function submission(int $categoryid, int $owncolours): array {
        return [
            'id' => $categoryid,
            'layout' => branding::LAYOUT_SOLID,
            'owncolours' => $owncolours,
            'bgcolour' => '#12484f',
            'inkcolour' => '#ffffff',
            'overlay' => 78,
            'tagline' => '',
            'cardsummary' => 'Texto do cartão',
            'defaultsort' => '',
            'publicsummary' => '',
            'publicdescription_editor' => ['text' => '', 'format' => FORMAT_HTML],
            'description_editor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'heroimage' => 0,
        ];
    }

    /**
     * The form rendered on its own page.
     *
     * @param int $categoryid The category.
     * @return string The HTML.
     */
    private static function rendered(int $categoryid): string {
        global $PAGE;
        // A page of its own per render, as each request has: a test renders three of them.
        $PAGE = new \moodle_page();
        $PAGE->set_context(\core\context\coursecat::instance($categoryid));
        $PAGE->set_url('/theme/dimensions/categoryhero.php', ['id' => $categoryid]);
        $form = new categoryhero(null, ['categoryid' => $categoryid]);
        $form->set_data(branding::form_data($categoryid));
        return $form->render();
    }

    /**
     * Unticked, the colours are neither validated nor stored, and the category inherits.
     *
     * The same submission with the box ticked is the control: the bad colour is refused there.
     */
    public function test_unticked_colours_are_neither_validated_nor_stored(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $parent = (int) $generator->create_category()->id;
        $categoryid = (int) $generator->create_category(['parent' => $parent])->id;
        branding::save($parent, (object) ['owncolours' => 1, 'bgcolour' => '#7a1f2b', 'inkcolour' => '#ffffff']);
        $bad = ['bgcolour' => 'crimson', 'inkcolour' => '#777777'];

        categoryhero::mock_submit($bad + self::submission($categoryid, 1));
        $this->assertFalse((new categoryhero(null, ['categoryid' => $categoryid]))->is_validated(), 'The control.');

        categoryhero::mock_submit($bad + self::submission($categoryid, 0));
        $form = new categoryhero(null, ['categoryid' => $categoryid]);
        $this->assertTrue($form->is_validated(), 'Colours nobody can edit were refused.');
        $row = branding::save($categoryid, $form->get_data());
        $this->assertSame(0, $row->owncolours);
        $payload = branding::for_category($categoryid);
        $this->assertSame(['#7a1f2b', '#ffffff'], [$payload['bgcolour'], $payload['inkcolour']]);
        $this->assertSame($parent, $payload['colourfrom']);
    }

    /**
     * Unticked, the two fields open on the inherited pair, are disabled by the box, and the sentence names the source.
     *
     * The source's name is escaped exactly once; a top-level category reads the theme's sentence;
     * and a manager who may not see the category above is told the pair comes from above, never
     * its name.
     */
    public function test_the_fields_show_the_inherited_pair_and_the_sentence_names_where_from(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $parent = (int) $generator->create_category(['name' => 'Brigada & Defesa'])->id;
        $categoryid = (int) $generator->create_category(['parent' => $parent])->id;
        $top = (int) $generator->create_category()->id;
        branding::save($parent, (object) ['owncolours' => 1, 'bgcolour' => '#7a1f2b', 'inkcolour' => '#ffffff']);

        $data = branding::form_data($categoryid);
        $this->assertSame(0, $data->owncolours);
        $this->assertSame('#7a1f2b', $data->bgcolour, 'The field does not show the inherited pair.');
        $html = self::rendered($categoryid);
        $this->assertStringContainsString(
            get_string('categoryhero_inheritedfrom', 'theme_dimensions', 'Brigada &amp; Defesa'),
            $html
        );
        $this->assertStringNotContainsString('&amp;amp;', $html, 'The source\'s name is escaped twice.');
        $this->assertMatchesRegularExpression('~<div class="dim-pair-preview"[^>]*background-color: #7a1f2b;~', $html);

        $quickform = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue(
            new categoryhero(null, ['categoryid' => $categoryid])
        );
        $rules = (new \ReflectionProperty($quickform, '_dependencies'))->getValue($quickform);
        $this->assertContains(
            'bgcolour',
            $rules['owncolours']['notchecked'][1] ?? [],
            'The background is not disabled by the box.'
        );
        $this->assertContains('inkcolour', $rules['owncolours']['notchecked'][1] ?? []);

        $this->assertStringContainsString(
            get_string('categoryhero_inheritedtheme', 'theme_dimensions'),
            self::rendered($top)
        );

        // A manager of the child alone, under a hidden parent: the pair is named as from above.
        \core_course_category::get($parent)->hide();
        $manager = $generator->create_user();
        $role = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($role, $manager->id, \core\context\coursecat::instance($categoryid));
        $this->setUser($manager);
        $html = self::rendered($categoryid);
        $this->assertStringContainsString(get_string('categoryhero_inheritedabove', 'theme_dimensions'), $html);
        $this->assertStringNotContainsString('Brigada', $html, 'A hidden category\'s name reached its child\'s editor.');
    }

    /**
     * The colour source above is not named to a manager of the child when it is unlisted for them.
     *
     * Core shows the unlisted parent to this viewer, so the name would be printed; the state
     * local_unlistedcourses keeps on it is the second condition. A manager of the ancestor is the
     * control: they hold a role there, so the same category is discoverable and its name appears.
     */
    public function test_an_unlisted_colour_source_is_not_named_to_a_manager_of_the_child_alone(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $parent = (int) $generator->create_category(['name' => 'Unlisted Source'])->id;
        $categoryid = (int) $generator->create_category(['parent' => $parent])->id;
        branding::save($parent, (object) ['owncolours' => 1, 'bgcolour' => '#7a1f2b', 'inkcolour' => '#ffffff']);
        category_discoverability::set_state($parent, category_discoverability::STATE_UNLISTED);
        $role = $DB->get_field('role', 'id', ['shortname' => 'manager']);

        $childmanager = $generator->create_user();
        role_assign($role, $childmanager->id, \core\context\coursecat::instance($categoryid));
        $this->setUser($childmanager);
        category_access::reset_caches();
        $html = self::rendered($categoryid);
        $this->assertStringContainsString(get_string('categoryhero_inheritedabove', 'theme_dimensions'), $html);
        $this->assertStringNotContainsString('Unlisted Source', $html, 'An unlisted category\'s name reached its child\'s editor.');

        $parentmanager = $generator->create_user();
        role_assign($role, $parentmanager->id, \core\context\coursecat::instance($parent));
        $this->setUser($parentmanager);
        category_access::reset_caches();
        $this->assertStringContainsString(
            get_string('categoryhero_inheritedfrom', 'theme_dimensions', 'Unlisted Source'),
            self::rendered($categoryid),
            'Control: with a role in the source the name is printed.'
        );
    }

    /**
     * The card text and the default organisation are offered, validated against their limits, and stored.
     */
    public function test_the_card_text_and_the_default_organisation_are_validated_and_stored(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        $this->assertSame(['', 'category', 'name', 'recent'], array_keys(categoryhero::defaultsort_options()));
        $this->assertSame(criteria::SORTS, array_keys(categoryhero::defaultsort_options()), 'The vocabulary is the address\'s.');
        foreach (
            [
                ['defaultsort' => 'popular'],
                ['cardsummary' => str_repeat('a', branding::CARDSUMMARY_MAXLENGTH + 1)],
            ] as $bad
        ) {
            $form = new categoryhero(null, ['categoryid' => $categoryid]);
            $errors = $form->validation($bad + self::submission($categoryid, 1), []);
            $this->assertArrayHasKey(array_key_first($bad), $errors, 'Accepted ' . json_encode($bad));
        }

        categoryhero::mock_submit(['defaultsort' => criteria::SORT_RECENT] + self::submission($categoryid, 1));
        $form = new categoryhero(null, ['categoryid' => $categoryid]);
        $this->assertTrue($form->is_validated());
        $row = branding::save($categoryid, $form->get_data());
        $this->assertSame(criteria::SORT_RECENT, $row->defaultsort);
        $this->assertSame('Texto do cartão', $row->cardsummary);
        $this->assertSame(criteria::SORT_RECENT, branding::form_data($categoryid)->defaultsort);

        $this->expectException(\invalid_parameter_exception::class);
        branding::save($categoryid, (object) ['defaultsort' => 'popular']);
    }
}
