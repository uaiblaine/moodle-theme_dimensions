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
 * Theme Dimensions - Tests of a category card's own text
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * Tests of a category card's own text: shown when set, the description's excerpt otherwise, escaped once.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(population::class)]
#[CoversClass(branding::class)]
final class card_text_test extends \advanced_testcase {
    use fixture;

    /**
     * The posters of a parent's page, by name.
     *
     * @param int $parentid The parent category.
     * @param bool $anonymous Whether a visitor asks.
     * @return array name => poster.
     */
    private static function posters(int $parentid, bool $anonymous = false): array {
        $options = ['withposters' => true];
        if ($anonymous) {
            $options['anonymous'] = true;
        }
        return array_column(population::resolve(new criteria($parentid, $options))->posters, null, 'name');
    }

    /**
     * A card with a text of its own shows it; a card without one shows its description's excerpt.
     *
     * Both children carry a core description, so the first card's text can only be its own text
     * and the second card is the fallback's control.
     */
    public function test_a_card_shows_its_own_text_and_falls_back_to_the_description(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $parent = (int) $generator->create_category()->id;
        $own = (int) $generator->create_category([
            'parent' => $parent,
            'name' => 'Own',
            'description' => '<p>A descrição interna da categoria, para quem está logado.</p>',
        ])->id;
        $fallback = (int) $generator->create_category([
            'parent' => $parent,
            'name' => 'Fallback',
            'description' => '<p>Cursos de resgate e salvamento.</p>',
        ])->id;
        branding::save($own, (object) ['cardsummary' => '  Formação de brigadistas civis  ']);

        $posters = self::posters($parent);
        $this->assertSame('Formação de brigadistas civis', $posters['Own']['description'], 'Trimmed, the card\'s own.');
        $this->assertTrue($posters['Own']['hasdescription']);
        $this->assertSame('Cursos de resgate e salvamento.', $posters['Fallback']['description']);

        // At the root the directory card goes through the same builder; the generator gives the parent a description,
        // so the card summary must win over it there too.
        branding::save($parent, (object) ['cardsummary' => 'Texto do diretório']);
        $directory = array_column(population::resolve(new criteria(0))->directory, 'description', 'id');
        $this->assertSame('Texto do diretório', $directory[$parent]);
    }

    /**
     * The card text is plain, in the spelling the template escapes once, and longer than the limit is refused.
     *
     * A bare ampersand is the fixture, as for the pair preview's name: markup is stripped in
     * either spelling and would prove nothing about the escaping. The rendered poster is the
     * other half - one escape, never two.
     */
    public function test_the_card_text_is_escaped_once_and_capped(): void {
        global $OUTPUT;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $parent = (int) $generator->create_category()->id;
        $child = (int) $generator->create_category(['parent' => $parent, 'name' => 'Child'])->id;
        branding::save($child, (object) ['cardsummary' => 'Saúde & <b>Segurança</b> do trabalho']);

        $poster = self::posters($parent)['Child'];
        $this->assertSame('Saúde & Segurança do trabalho', $poster['description'], 'Not the plain spelling.');
        $html = $OUTPUT->render_from_template('theme_dimensions/category_directory_card', $poster);
        $this->assertStringContainsString('Saúde &amp; Segurança do trabalho', $html, 'Not escaped once.');
        $this->assertStringNotContainsString('&amp;amp;', $html, 'Escaped twice.');
        $this->assertStringNotContainsString('<b>', $html, 'Markup reached the card.');

        $this->assertSame(160, branding::CARDSUMMARY_MAXLENGTH);
        $this->assertSame(population::EXCERPT_LENGTH, branding::CARDSUMMARY_MAXLENGTH, 'The card text outgrew the card.');
        branding::save($child, (object) ['cardsummary' => str_repeat('é', branding::CARDSUMMARY_MAXLENGTH)]);
        $this->assertSame(str_repeat('é', 160), branding::record($child)->cardsummary, 'The limit counts characters.');
        $this->expectException(\invalid_parameter_exception::class);
        branding::save($child, (object) ['cardsummary' => str_repeat('é', branding::CARDSUMMARY_MAXLENGTH + 1)]);
    }

    /**
     * A visitor's text-only poster withholds the card text, as it withholds the description.
     *
     * The public sibling with the same text is the control: without it the assertion passes
     * against a builder that drew no text for anybody.
     */
    public function test_a_visitors_text_poster_withholds_the_card_text(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Root']);
        $this->set_category_state((int) $root->id, category_discoverability::STATE_PUBLIC);
        $listed = $generator->create_category(['parent' => $root->id, 'name' => 'Listed', 'sortorder' => 10]);
        $public = $generator->create_category(['parent' => $root->id, 'name' => 'Public', 'sortorder' => 20]);
        $this->set_category_state((int) $public->id, category_discoverability::STATE_PUBLIC);
        $this->setAdminUser();
        foreach ([$listed, $public] as $child) {
            $course = $generator->create_course(['category' => $child->id]);
            $this->set_course_state((int) $course->id, discoverability::STATE_PUBLIC);
            branding::save((int) $child->id, (object) ['cardsummary' => 'O que esta área oferece']);
        }

        $this->become(null);
        $posters = self::posters((int) $root->id, true);
        $this->assertTrue($posters['Listed']['istext'], 'Precondition: the listed child is a text poster.');
        $this->assertSame('', $posters['Listed']['description']);
        $this->assertFalse($posters['Listed']['hasdescription']);
        $this->assertSame('O que esta área oferece', $posters['Public']['description'], 'The control draws its text.');
    }
}
