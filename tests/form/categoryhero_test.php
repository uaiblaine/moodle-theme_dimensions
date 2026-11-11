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
 * Theme Dimensions - Tests of the category appearance form
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\form;

use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\category\branding;
use theme_dimensions\local\category\unavailable_exception;
use theme_dimensions\local\landingfields;

/**
 * Tests of the category appearance form: the vocabulary is the form's, and nothing outside it passes.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(categoryhero::class)]
final class categoryhero_test extends \advanced_testcase {
    /**
     * A submission inside the vocabulary.
     *
     * @param int $categoryid The category.
     * @return array
     */
    private static function submission(int $categoryid): array {
        return [
            'id' => $categoryid,
            'layout' => branding::LAYOUT_SOLID,
            // The pair is the category's own, so the colour fields are validated and stored.
            'owncolours' => 1,
            'bgcolour' => '#12484f',
            'inkcolour' => '#ffffff',
            'overlay' => 78,
            'tagline' => 'Uma frase',
            'publicsummary' => 'Uma descrição pública curta',
            'publicdescription_editor' => ['text' => '<p>Público</p>', 'format' => FORMAT_HTML],
            'description_editor' => ['text' => '<p>Interna</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
            'heroimage' => 0,
        ];
    }

    /**
     * The QuickForm behind a moodleform, whose elements this file reads.
     *
     * moodleform keeps it protected and exposes no accessor, so a test asking what the form
     * really declared - an element's options, an attribute - has to reflect it out. Reading the
     * element rather than the rendered page is what makes those assertions survive a theme
     * change while still failing when the declaration moves.
     *
     * @param categoryhero $form The form.
     * @return \MoodleQuickForm The form it built.
     */
    private static function quickform(categoryhero $form): \MoodleQuickForm {
        $property = new \ReflectionProperty(\moodleform::class, '_form');
        return $property->getValue($form);
    }

    /**
     * The form's elements in declaration order, as "type:name".
     *
     * The order is the whole content of the section split: a field is in a fieldset because it
     * was declared between that header and the next one, and nothing else records which section
     * a row belongs to. The pair preview has no name of its own (it is an `html` pseudo-element),
     * so it appears as "html:".
     *
     * @param categoryhero $form The form.
     * @return string[] One entry per element, in the order the definition declared them.
     */
    private static function element_order(categoryhero $form): array {
        $order = [];
        foreach (self::quickform($form)->_elements as $element) {
            $order[] = $element->getType() . ':' . (string) $element->getName();
        }
        return $order;
    }

    /**
     * The colours are their own section, after the crests, and the tagline stays in Appearance.
     *
     * The split is the two-column arrangement. A grid over the colours fieldset puts the fields in
     * one track and the contrast panel in the other, so which fieldset a row is in is a layout
     * decision, and the only thing that records it is the order below. The preview is the last row
     * of the section, which is the reading order the design asks for: fields, then the pair on the
     * band, then the panel the module appends after it.
     */
    public function test_the_colours_are_their_own_section_after_the_crests(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        $order = self::element_order(new categoryhero(null, ['categoryid' => $categoryid]));
        $positions = [];
        foreach (
            [
                'appearance' => 'header:appearanceheader',
                'lastcrest' => 'filemanager:' . branding::AREA_BADGES[2],
                'tagline' => 'text:tagline',
                'colours' => 'header:coloursheader',
                'bg' => 'text:bgcolour',
                'ink' => 'text:inkcolour',
                'overlay' => 'select:overlay',
                'preview' => 'html:',
                'public' => 'header:publicheader',
            ] as $what => $entry
        ) {
            $index = array_search($entry, $order, true);
            $this->assertIsInt($index, $entry . ' is no longer on the form at all.');
            $positions[$what] = $index;
        }

        $this->assertGreaterThan(
            $positions['lastcrest'],
            $positions['colours'],
            'The colours section opens before the crests, which puts the file managers inside it.'
        );
        $this->assertGreaterThan(
            $positions['appearance'],
            $positions['tagline'],
            'The tagline is no longer in the appearance section.'
        );
        $this->assertLessThan(
            $positions['colours'],
            $positions['tagline'],
            'The tagline was declared after the colours header, so it is inside the colours section.'
        );
        foreach (['bg', 'ink', 'overlay', 'preview'] as $inside) {
            $this->assertGreaterThan($positions['colours'], $positions[$inside], $inside . ' is above the section.');
            $this->assertLessThan($positions['public'], $positions[$inside], $inside . ' fell into the public section.');
        }
        $this->assertGreaterThan(
            $positions['overlay'],
            $positions['preview'],
            'The preview is not the last row of the section, so the panel would not follow it.'
        );
        $this->assertSame(
            1,
            count(array_keys($order, 'html:', true)),
            'A second raw block is on the form: the grid places exactly one of them in the left track.'
        );
    }

    /**
     * The overlay's dependency still names a field in the section above, and still hides it.
     *
     * The rule crosses a fieldset boundary, which reads like a thing that cannot work and is fine:
     * the dependency is registered on the form and M.form.initFormDependencies runs over the whole
     * of it. Asserted here because nothing else would notice it being quietly dropped - the field
     * would simply always be visible.
     */
    public function test_the_overlay_still_hides_itself_from_the_layout_in_the_section_above(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        $quickform = self::quickform(new categoryhero(null, ['categoryid' => $categoryid]));
        $hideifs = new \ReflectionProperty($quickform, '_hideifs');
        $rules = $hideifs->getValue($quickform);

        /* Keyed by the field the rule depends on, which is the one in the section above. */
        $this->assertArrayHasKey('layout', $rules, 'Nothing on the form depends on the layout any more.');
        $this->assertArrayHasKey('neq', $rules['layout'], 'The rule is no longer a "not equal" one.');
        $this->assertArrayHasKey(
            branding::LAYOUT_IMAGE,
            $rules['layout']['neq'],
            'The rule no longer names the image layout, so the overlay hides on the wrong one.'
        );
        $this->assertContains(
            'overlay',
            $rules['layout']['neq'][branding::LAYOUT_IMAGE],
            'The overlay is shown for every layout again, a fieldset away from the choice that decides it.'
        );
    }

    /**
     * The form accepts the vocabulary and hands the choices over as submitted.
     */
    public function test_the_form_accepts_the_vocabulary(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        categoryhero::mock_submit(self::submission($categoryid));
        $form = new categoryhero(null, ['categoryid' => $categoryid]);

        $this->assertTrue($form->is_validated());
        $data = $form->get_data();
        $this->assertSame('#12484f', $data->bgcolour);
        $this->assertSame('#ffffff', $data->inkcolour);
        $this->assertSame(branding::LAYOUT_SOLID, $data->layout);
        $this->assertSame(78, (int) $data->overlay);
        $this->assertSame('Uma frase', $data->tagline);
        $this->assertSame('Uma descrição pública curta', $data->publicsummary);
        $this->assertSame('<p>Público</p>', $data->publicdescription_editor['text']);
        $this->assertSame($categoryid, (int) $data->id);
    }

    /**
     * A value outside its vocabulary, or a tagline past the limit, is refused with an error on its element.
     */
    public function test_the_form_refuses_anything_outside_the_vocabulary(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        foreach (
            [
                ['bgcolour' => 'crimson'],
                ['bgcolour' => ''],
                ['inkcolour' => 'rgb(1,2,3)'],
                ['layout' => 'banner'],
                ['overlay' => 45],
                ['tagline' => str_repeat('a', branding::TAGLINE_MAXLENGTH + 1)],
                ['publicsummary' => str_repeat('a', branding::PUBLICSUMMARY_MAXLENGTH + 1)],
            ] as $bad
        ) {
            categoryhero::mock_submit($bad + self::submission($categoryid));
            $form = new categoryhero(null, ['categoryid' => $categoryid]);
            $this->assertFalse($form->is_validated(), 'Accepted ' . json_encode($bad));
            $this->assertNull($form->get_data());
        }
    }

    /**
     * The overlay is checked as submitted, not as cast: with 0 in the vocabulary, "" and a word
     * would read as "Transparent" under (int).
     *
     * Asked of validation() itself, because the select swallows an unknown choice before it gets
     * there. The controls are the four choices, which must pass in both spellings.
     */
    public function test_the_overlay_is_validated_as_submitted_and_not_as_cast(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $form = new categoryhero(null, ['categoryid' => $categoryid]);

        foreach (['', 'transparent', '78.5', 45, null] as $bad) {
            $errors = $form->validation(['overlay' => $bad] + self::submission($categoryid), []);
            $this->assertArrayHasKey('overlay', $errors, 'Accepted ' . json_encode($bad));
        }
        foreach ([0, '0', 60, '78', 90] as $good) {
            $errors = $form->validation(['overlay' => $good] + self::submission($categoryid), []);
            $this->assertArrayNotHasKey('overlay', $errors, 'Refused ' . json_encode($good));
        }
    }

    /**
     * The layout and overlay options cover the vocabulary the server accepts, and nothing else.
     *
     * The overlay half includes 0, "Transparent": the option and the constant have to move
     * together, or the editor offers a value validate() refuses, or the other way round - a
     * stored 0 that reads back as the default and quietly repaints a category.
     */
    public function test_the_options_cover_the_vocabulary(): void {
        $this->assertSame(branding::LAYOUTS, array_keys(categoryhero::layout_options()));
        $this->assertSame(branding::OVERLAYS, array_keys(categoryhero::overlay_options()));
        $this->assertSame(
            [0, 60, 78, 90],
            array_keys(categoryhero::overlay_options()),
            'The transparent choice is the first of the four overlay strengths.'
        );
        $this->assertSame(
            get_string('categoryhero_overlay_transparent', 'theme_dimensions'),
            categoryhero::overlay_options()[0]
        );
        $this->assertCount(
            count(branding::OVERLAYS),
            array_unique(categoryhero::overlay_options()),
            'One label per overlay.'
        );
    }

    /**
     * An invalid colour and a pair under the floor are refused, each on its own element and with its own message.
     *
     * Two different refusals, and the difference is what a manager acts on: "that is not a
     * colour" is a typing mistake, and "these two read 3.90:1" is a design decision they have
     * to make with the panel beside them. The floor is asked only of a pair that parsed,
     * so a half-typed value never produces a ratio nobody can act on.
     */
    public function test_the_colour_fields_report_a_bad_value_and_a_bad_pair_apart(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        categoryhero::mock_submit(['bgcolour' => 'crimson'] + self::submission($categoryid));
        $form = new categoryhero(null, ['categoryid' => $categoryid]);
        $errors = $form->validation(['bgcolour' => 'crimson'] + self::submission($categoryid), []);
        $this->assertSame(
            get_string('categoryhero_invalidcolour', 'theme_dimensions'),
            $errors['bgcolour'] ?? null
        );
        $this->assertArrayNotHasKey('inkcolour', $errors, 'A value that did not parse yields no ratio.');

        $pair = ['bgcolour' => '#818181', 'inkcolour' => '#ffffff'] + self::submission($categoryid);
        $ratio = branding::contrast('#818181', '#ffffff');
        $this->assertLessThan(branding::FLOOR, $ratio, 'The precondition of the refusal.');
        $form = new categoryhero(null, ['categoryid' => $categoryid]);
        $errors = $form->validation($pair, []);
        $this->assertArrayNotHasKey('bgcolour', $errors, 'Both values parsed; only the PAIR is wrong.');
        $this->assertSame(
            get_string('categoryhero_contrastfloor', 'theme_dimensions', (object) [
                'ratio' => branding::format_ratio($ratio),
                'floor' => branding::format_ratio(branding::FLOOR),
            ]),
            $errors['inkcolour'] ?? null
        );
        $this->assertStringContainsString('3.90', $errors['inkcolour'], 'The measured ratio, not a typed one.');
        /* And the floor itself is substituted, not typed into the sentence: a language pack
           spelling it by hand puts the pack's decimal separator beside the formatter's, and a
           change to FLOOR would leave two packs quietly naming the old number. */
        $this->assertStringContainsString(
            branding::format_ratio(branding::FLOOR),
            $errors['inkcolour'],
            'The minimum in the message is the constant, printed by the same formatter as the ratio.'
        );

        // A pair just over the floor passes the whole form, which is the control of both refusals.
        categoryhero::mock_submit(
            ['bgcolour' => '#757575', 'inkcolour' => '#ffffff'] + self::submission($categoryid)
        );
        $form = new categoryhero(null, ['categoryid' => $categoryid]);
        $this->assertTrue($form->is_validated());
    }

    /**
     * The whole form, rendered, with the context and the URL the editor's own script sets.
     *
     * Both are needed: the file managers read the page's context, and tiny_autosave reads the
     * page's URL while the description editor renders, which without this is an "Unexpected
     * debugging() call" per render, and that fails the test.
     *
     * @param categoryhero $form The form.
     * @param int $categoryid The category it edits.
     * @return string The rendered form.
     */
    private static function rendered(categoryhero $form, int $categoryid): string {
        global $PAGE;

        $PAGE->set_context(\core\context\coursecat::instance($categoryid));
        $PAGE->set_url('/theme/dimensions/categoryhero.php', ['id' => $categoryid]);
        return $form->render();
    }

    /**
     * One fieldset of the rendered form, from its opening tag to the end of the string.
     *
     * Scoped on purpose: an assertion made over the whole page would match the same words in the
     * section above or below and pass while the row is in the wrong one.
     *
     * The cut is the next section fieldset and not the next `<fieldset`: core's filemanager,
     * filepicker, group and date elements each wrap themselves in a borderless fieldset of their
     * own, so a lazier cut ends the appearance section at its background image and reads every
     * row after it as missing.
     *
     * @param string $html The rendered form.
     * @param string $id The fieldset's id attribute.
     * @return string The fieldset, up to the next section's opening tag.
     */
    private static function fieldset(string $html, string $id): string {
        $pattern = '~<fieldset[^>]*\bid="' . preg_quote($id, '~') . '"[^>]*>(.*?)(?=<fieldset class="clearfix fitem|$)~s';
        $found = preg_match($pattern, $html, $m);
        return $found === 1 ? $m[0] : '';
    }

    /**
     * The colours really are a fieldset with a container of its own, which is what the grid needs.
     *
     * The stylesheet's two-column rule names core's own markup -
     * `fieldset.dim-colours--split > .fcontainer` - and nothing in the pipeline reads a selector
     * against the page it is written for. So the markup is measured here rather than assumed:
     * core opens a fieldset carrying the header's id and
     * lib/form/templates/element-header.mustache puts a `.fcontainer` inside it whose id is that
     * same id with "container" appended, with every row of the section as a child of it. If a
     * core release changes either, this test is what says so, and scss/categoryhero.scss names
     * this test above the rule.
     */
    public function test_the_colours_are_a_fieldset_whose_container_the_grid_can_place(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        $html = self::rendered(new categoryhero(null, ['categoryid' => $categoryid]), $categoryid);
        $found = preg_match('~<fieldset[^>]*\bid="id_coloursheader"[^>]*>~', $html, $opening);
        $this->assertSame(1, $found, 'The colours section is not a fieldset carrying the header\'s id.');
        $this->assertStringContainsString(
            'collapsible',
            $opening[0],
            'The section is no longer collapsible, so the accordion this form asked for is gone.'
        );

        $fieldset = self::fieldset($html, 'id_coloursheader');
        $this->assertNotSame('', $fieldset);
        $this->assertMatchesRegularExpression(
            '~<div id="id_coloursheadercontainer" class="fcontainer[^"]*"~',
            $fieldset,
            'The container the grid is drawn on is not core\'s .fcontainer inside this fieldset.'
        );
        $this->assertStringContainsString(
            '<legend class="visually-hidden">' . get_string('categoryhero_colours', 'theme_dimensions'),
            $fieldset,
            'The fieldset has no legend naming it, which is what a Behat "fieldset" locator reads.'
        );

        // The three fields and the preview are in THIS fieldset, and the tagline is not.
        foreach (['bgcolour', 'inkcolour', 'overlay'] as $name) {
            $this->assertStringContainsString('name="' . $name . '"', $fieldset, $name . ' left the colours section.');
        }
        $this->assertStringContainsString(
            'data-region="dim-pair-preview"',
            $fieldset,
            'The pair preview is not inside the section the grid places.'
        );
        $this->assertStringNotContainsString(
            'name="tagline"',
            $fieldset,
            'The tagline is inside the colours section, where the grid would put it beside the panel.'
        );
        $this->assertStringContainsString(
            'name="tagline"',
            self::fieldset($html, 'id_appearanceheader'),
            'The tagline left the appearance section altogether.'
        );
    }

    /**
     * The preview carries the stored pair, and a stored value that is not a hex never reaches it.
     *
     * The style attribute is a CSS context, which Mustache's escaping does not protect - the same
     * sink as the hero's inline style, and the same one sanitiser behind it. The poisoned row is
     * written with $DB directly, because that is the only way to put a value in the column that
     * branding::save() would have refused: a migration, a hand edit, an older release. Both halves
     * are asserted inside the preview's own opening tag, never over the page, and the default is
     * what a bad value reads as - which is also what the field above it shows, so the band and the
     * fields can never disagree.
     */
    public function test_the_preview_paints_only_a_sanitised_pair(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        // The stored pair, saved the way the editor saves it.
        branding::save($categoryid, (object) [
            'layout' => branding::LAYOUT_SOLID,
            'bgcolour' => '#12484f',
            'inkcolour' => '#ffffff',
            'overlay' => 78,
            'tagline' => '',
            'publicsummary' => '',
            'publicdescription_editor' => ['text' => '', 'format' => FORMAT_HTML],
        ]);
        $preview = self::preview_tag(self::rendered(new categoryhero(null, ['categoryid' => $categoryid]), $categoryid));
        $this->assertStringContainsString('background-color: #12484f;', $preview, 'The stored band colour is not on it.');
        $this->assertStringContainsString('color: #ffffff;', $preview, 'The stored ink is not on it.');

        /* And now a row no save of this plugin could have written. Both values are six characters
           because the column is char(7), which is itself part of the answer: what a stored value
           could do to a style attribute is bounded by the column, and what keeps it out is the
           sanitiser. The first would close the declaration and open a rule of its own. */
        $DB->set_field(branding::TABLE, 'bgcolour', '#fff;}', ['categoryid' => $categoryid]);
        $DB->set_field(branding::TABLE, 'inkcolour', 'rgb(1)', ['categoryid' => $categoryid]);
        $preview = self::preview_tag(self::rendered(new categoryhero(null, ['categoryid' => $categoryid]), $categoryid));

        $this->assertStringNotContainsString(';}', $preview, 'The stored bytes reached the style attribute.');
        $this->assertStringNotContainsString('rgb(', $preview, 'A colour outside the vocabulary reached the attribute.');
        $this->assertStringContainsString(
            'background-color: ' . branding::DEFAULT_BG . ';',
            $preview,
            'A band colour that does not parse must read as the default, which is what the field shows too.'
        );
        $this->assertStringContainsString('color: ' . branding::DEFAULT_INK . ';', $preview);
        $this->assertMatchesRegularExpression(
            '~style="(background-color: #[0-9a-f]{6}; color: #[0-9a-f]{6};)"~',
            $preview,
            'The attribute holds something other than the two colours: ' . $preview
        );
    }

    /**
     * The preview names the category, escaped exactly once.
     *
     * The name travels in the plain spelling and the template's double stash escapes it, which is
     * why the form asks format_string() not to escape for it. A bare
     * ampersand is the fixture, because tag-shaped input is stripped identically in both modes
     * and would prove nothing.
     */
    public function test_the_preview_names_the_category_escaped_once(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category(['name' => 'Brigada & Defesa'])->id;

        $html = self::rendered(new categoryhero(null, ['categoryid' => $categoryid]), $categoryid);
        $found = preg_match(
            '~<div class="dim-pair-preview"[^>]*>(.*?)</div>~s',
            $html,
            $block
        );
        $this->assertSame(1, $found, 'The preview block is not on the form.');
        $this->assertStringContainsString('Brigada &amp; Defesa', $block[1], 'The name is not escaped once.');
        $this->assertStringNotContainsString('&amp;amp;', $block[1], 'The name is escaped twice.');
        $this->assertStringContainsString(
            get_string('categoryhero_pairpreview', 'theme_dimensions'),
            $block[1],
            'The caption is not visible text under the name.'
        );
    }

    /**
     * The opening tag of the pair preview, which is where its two colours are.
     *
     * @param string $html The rendered form.
     * @return string The opening tag, or the empty string when there is none.
     */
    private static function preview_tag(string $html): string {
        $found = preg_match('~<div class="dim-pair-preview"[^>]*>~', $html, $m);
        return $found === 1 ? $m[0] : '';
    }

    /**
     * A pair under the floor opens the colours section, past a manager who had collapsed it.
     *
     * Core expands the fieldset holding a validation error, and this is the one refusal of this
     * form a manager can meet on a section they had folded away: the message would then be
     * attached to a field nothing on screen shows. The control is what makes the assertion mean
     * anything - the same collapsed submission with a pair that passes stays collapsed - because
     * the section is declared expanded, so a test that only submitted a bad pair would pass with
     * core's whole mechanism removed.
     */
    public function test_a_refused_pair_opens_the_colours_section_a_manager_had_collapsed(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $collapsed = ['mform_isexpanded_id_coloursheader' => 0];

        categoryhero::mock_submit(
            $collapsed + ['bgcolour' => '#818181', 'inkcolour' => '#ffffff'] + self::submission($categoryid)
        );
        $form = new categoryhero(null, ['categoryid' => $categoryid]);
        $this->assertFalse($form->is_validated(), 'Precondition: this pair is under the floor and is refused.');
        $html = self::rendered($form, $categoryid);
        $found = preg_match('~<fieldset[^>]*\bid="id_coloursheader"[^>]*>~', $html, $opening);
        $this->assertSame(1, $found);
        $this->assertStringNotContainsString(
            'collapsed',
            $opening[0],
            'The refusal left the colours section folded, with the message on a field nobody can see.'
        );

        // The control: the same folded submission, with a pair the form accepts.
        categoryhero::mock_submit($collapsed + self::submission($categoryid));
        $form = new categoryhero(null, ['categoryid' => $categoryid]);
        $this->assertTrue($form->is_validated(), 'Precondition: this pair passes.');
        $found = preg_match(
            '~<fieldset[^>]*\bid="id_coloursheader"[^>]*>~',
            self::rendered($form, $categoryid),
            $opening
        );
        $this->assertSame(1, $found);
        $this->assertStringContainsString(
            'collapsed',
            $opening[0],
            'The section reopens whatever the manager did, so the assertion above proves nothing.'
        );
    }

    /**
     * The public description field stops at 200 characters in the browser, not only at the server.
     *
     * The field must not suggest that it accepts more. A server-side refusal alone lets somebody
     * write four hundred characters and learn at the save that half of them were never going to be
     * shown, so the attribute is what the field itself enforces and this asserts it is rendered.
     * The refusal behind it is tested above, for a browser that ignores the attribute.
     */
    public function test_the_public_description_field_carries_its_limit_as_an_attribute(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        $form = new categoryhero(null, ['categoryid' => $categoryid]);
        $html = self::quickform($form)->getElement('publicsummary')->toHtml();

        $found = preg_match('~<input[^>]*name="publicsummary"[^>]*>~', $html, $matches);
        $this->assertSame(1, $found, 'The public description field is not rendered as a text input.');
        $this->assertMatchesRegularExpression(
            '~maxlength="' . branding::PUBLICSUMMARY_MAXLENGTH . '"~',
            $matches[0],
            'The field itself does not stop at the limit: ' . $matches[0]
        );
        $this->assertSame(
            200,
            branding::PUBLICSUMMARY_MAXLENGTH,
            'The limit is 200: the column is char(200) and the help text says so.'
        );
    }

    /**
     * The public page editor's media controls may hand back an external address and nothing else.
     *
     * Four values, each load-bearing. With `maxfiles` at 0 the multimedia button and the media
     * manager cannot open at all: lib/form/editor.php builds the file-picker options only when it
     * is non-zero, and tiny_media's embed dialogue dereferences them unguarded where its image
     * dialogue tests for undefined first. So `maxfiles => 1` is what makes the button work, and
     * the other three are what keep the draft area it mints unusable: FILE_EXTERNAL drops every
     * repository that cannot return an address - `upload` among them, which is what both dialogues
     * compute their drop zone from - and the two byte limits refuse anything that reached a
     * repository anyway.
     *
     * An image pasted into the body stores no file, but leaves a data: URI in the submitted text,
     * which {@see test_an_image_pasted_into_the_public_page_text_is_refused_on_its_own_field} covers.
     *
     * Note FILE_EXTERNAL is 1 and FILE_INTERNAL is 2 (repository/lib.php), the reverse of the
     * order the names suggest: the control below reads the constants, never the numbers.
     */
    public function test_the_public_page_editor_returns_external_addresses_only(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        $form = new categoryhero(null, ['categoryid' => $categoryid]);
        $element = self::quickform($form)->getElement('publicdescription_editor');
        $options = new \ReflectionProperty($element, '_options');
        $values = $options->getValue($element);

        $this->assertSame(
            1,
            (int) $values['maxfiles'],
            'At zero, core builds no file-picker options and tiny_media\'s multimedia dialogue throws.'
        );
        $this->assertSame(
            FILE_EXTERNAL,
            (int) $values['return_types'],
            'An internal return type would put the upload repository back and with it a drop zone.'
        );
        $this->assertSame(
            0,
            (int) $values['return_types'] & FILE_INTERNAL,
            'Control: the internal type really is off, which the element default of 15 has on.'
        );
        $this->assertSame(1, (int) $values['maxbytes'], 'Nothing may be uploaded into the draft area this mints.');
        $this->assertSame(1, (int) $values['areamaxbytes'], 'And the area itself may not grow past a byte.');
        $this->assertSame(
            categoryhero::publictext_editor_options(),
            [
                'maxfiles' => 1,
                'maxbytes' => 1,
                'areamaxbytes' => 1,
                'return_types' => FILE_EXTERNAL,
            ],
            'The helper and the element are one option set: the element is built from it.'
        );
    }

    /**
     * An image pasted or dropped into the public page text is refused, on its own field.
     *
     * The editor offers no upload path at all - no drop zone in either dialogue, no upload
     * repository - but a browser can still put a picture into the body by a paste or a
     * drag-and-drop, and TinyMCE hands those to its own upload handler. That handler fails here
     * (`{"errorcode":"invalidrepositoryid"}`, because there is no repository of type upload for
     * it to name), and TinyMCE then leaves the image inline: `blob:` in the live DOM and
     * `data:image/png;base64,...` in the value the form submits.
     *
     * Neither address can be read by anybody else. `data:` and `blob:` are not in core's
     * HTMLPurifier scheme list, so the public page silently drops the element while the row
     * keeps the base64; a draftfile address belongs to one person's draft area, which core's
     * cron purges after four days. So the form refuses, with a message saying what to do instead.
     *
     * The controls are the point: an ordinary external image, and the very anchor the multimedia
     * dialogue inserts, both pass. Without them this would read exactly the same against a form
     * that refuses every public text.
     */
    public function test_an_image_pasted_into_the_public_page_text_is_refused_on_its_own_field(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $data = self::submission($categoryid);

        foreach (
            [
                'the data: URI a pasted image submits' =>
                    '<p><img class="img-fluid" src="data:image/png;base64,iVBORw0KGg=="></p>',
                'the blob: URI the live editor holds' =>
                    '<p><img src="blob:http://localhost:9502/68eb9fb3-8110"></p>',
                'a draft file, which nobody else can fetch' =>
                    '<p><img src="http://localhost:9502/draftfile.php/5/user/draft/602834227/a.png"></p>',
            ] as $what => $text
        ) {
            $data['publicdescription_editor'] = ['text' => $text, 'format' => FORMAT_HTML];
            categoryhero::mock_submit($data);
            $form = new categoryhero(null, ['categoryid' => $categoryid]);
            $this->assertFalse($form->is_validated(), 'Accepted ' . $what . '.');
            $this->assertSame(
                get_string('categoryhero_publictext_nofiles', 'theme_dimensions'),
                self::quickform($form)->_errors['publicdescription_editor'] ?? null,
                'The message belongs on the field the manager has to fix: ' . $what
            );
        }

        foreach (
            [
                'the anchor the multimedia dialogue inserts' =>
                    '<p><a class="external-media-provider" href="https://www.youtube.com/watch?v=aqz-KE-bpKQ">v</a></p>',
                'a direct address of a film' =>
                    '<p><a class="external-media-provider" href="https://example.org/sample-5s.mp4">s</a></p>',
                'an ordinary image published elsewhere' => '<p><img src="https://example.org/crest.png" alt="x"></p>',
                'prose that merely says data: and blob:' => '<p>Sobre data: e blob: nada a declarar.</p>',
            ] as $what => $text
        ) {
            $data['publicdescription_editor'] = ['text' => $text, 'format' => FORMAT_HTML];
            categoryhero::mock_submit($data);
            $form = new categoryhero(null, ['categoryid' => $categoryid]);
            $this->assertTrue($form->is_validated(), 'Control refused: ' . $what);
        }
    }

    /**
     * The three crests are three single-file managers, each named after its file area.
     *
     * The name is the contract: branding::form_data() prepares a draft area under it and
     * branding::save() reads the submission back under it, so an element renamed here would
     * store a manager's crest nowhere at all, with the page still saving and the form still
     * reopening empty. The options are the landing page's own limit and vocabulary, read from its
     * constant rather than restated, because a crest a manager may put on a course must not be
     * refused on the course's category.
     *
     * A file manager and not a picker: a picker can only ever replace, so a crest uploaded once
     * could never be taken away. maxfiles is what holds the slot to one file, and it is asserted
     * below.
     */
    public function test_the_three_crests_are_filemanagers_named_after_their_areas(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        $form = new categoryhero(null, ['categoryid' => $categoryid]);
        $quickform = self::quickform($form);

        $this->assertCount(3, branding::AREA_BADGES, 'Precondition: three slots, three file managers.');
        foreach (branding::AREA_BADGES as $position => $area) {
            $element = $quickform->getElement($area);
            $this->assertSame(
                'filemanager',
                $element->getType(),
                $area . ' is a file manager: a picker offers no way to remove the crest it holds.'
            );
            $this->assertSame(
                get_string('categoryhero_badge' . ($position + 1), 'theme_dimensions'),
                $element->getLabel(),
                'The label is the slot\'s own, so the order on screen is the order on the page.'
            );
        }

        // The options, against the landing page's own numbers.
        $options = branding::badge_options();
        $this->assertSame(landingfields::CREST_MAXBYTES, (int) $options['maxbytes']);
        $this->assertSame(['web_image'], $options['accepted_types']);
        $this->assertSame(1, (int) $options['maxfiles'], 'One file per slot: the slot IS the position.');

        // And the help button of every one of them names a string both packs carry.
        foreach (branding::AREA_BADGES as $position => $area) {
            $this->assertTrue(
                get_string_manager()->string_exists('categoryhero_badge' . ($position + 1) . '_help', 'theme_dimensions'),
                'A help button with no string is a debugging notice on the page.'
            );
        }
    }

    /**
     * One list builds the five single-file rows, and no row of this form is a file picker.
     *
     * branding::single_file_areas() is what the form, form_data() and save() all read, so an
     * element offered with one option set, a draft prepared with a second and an area written
     * back with a third cannot happen: they are the same array. The loop below compares each element's
     * accepted types with the list's, expanded into extensions: tool_imagepicker's element, where it is
     * installed, stores the list's 'web_image' that way, and the set is what is accepted.
     *
     * The second half needs the hero image beside it as the control: every file row of this form
     * is a manager. A file picker shows a name, offers no thumbnail and has no control that
     * removes what it holds, so a cover, a film or a crest uploaded once could never be taken
     * away.
     *
     * Changes that must make it fail: handing an element an option set of its own, such as a second file
     * or folders, in place of the list's.
     */
    public function test_one_list_builds_the_five_single_file_rows_and_none_of_them_is_a_picker(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        $areas = branding::single_file_areas();
        $this->assertSame(
            array_merge([branding::AREA_COVER, branding::AREA_COVERVIDEO], branding::AREA_BADGES),
            array_keys($areas),
            'The list is the still, the film and the three crests, in the order of the rows.'
        );

        $form = new categoryhero(null, ['categoryid' => $categoryid]);
        $quickform = self::quickform($form);
        $property = new \ReflectionProperty(\MoodleQuickForm_filemanager::class, '_options');
        $typesutil = new \core_form\filetypes_util();
        foreach ($areas as $area => $options) {
            $element = $quickform->getElement($area);
            $this->assertSame('filemanager', $element->getType(), $area . ' is not a file manager.');
            // What the element was built with, not what the list says: the element is what a person uploads into.
            $built = $property->getValue($element);
            $this->assertSame(1, (int) $built['maxfiles'], $area . ' would hold more than one file.');
            $this->assertSame(0, (int) $built['subdirs'], $area . ' would offer folders.');
            $this->assertEqualsCanonicalizing(
                $typesutil->expand($options['accepted_types']),
                $typesutil->expand($built['accepted_types']),
                $area . ': the element was built with an option set of its own rather than with the list\'s.'
            );
        }

        $types = [];
        foreach ($quickform->_elements as $element) {
            $types[] = $element->getType();
        }
        $this->assertNotContains('filepicker', $types, 'A file picker cannot remove the file it holds.');
        $this->assertSame(
            6,
            count(array_filter($types, static fn(string $type): bool => $type === 'filemanager')),
            'Five single-file areas and the hero image, every one a file manager.'
        );
    }

    /**
     * The card cover and its video are file managers between the hero image and the crests.
     *
     * Named after their file areas, on the crests' own convention: branding::form_data() prepares
     * and branding::save() reads the draft under that name, so an element renamed here would store
     * a manager's file nowhere at all with the page still saving and the form reopening empty.
     * The position is asserted because it is the only thing recording which section a row is in,
     * and these two belong with the hero image rather than with the colours.
     */
    public function test_the_card_cover_and_its_video_are_filemanagers_between_the_image_and_the_crests(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        $form = new categoryhero(null, ['categoryid' => $categoryid]);
        $quickform = self::quickform($form);
        $order = self::element_order($form);

        foreach (
            [
                branding::AREA_COVER => 'categoryhero_cover',
                branding::AREA_COVERVIDEO => 'categoryhero_covervideo',
            ] as $area => $key
        ) {
            $element = $quickform->getElement($area);
            $this->assertSame(
                'filemanager',
                $element->getType(),
                $area . ' is a file manager, which is what lets a manager take the file away again.'
            );
            $this->assertSame(get_string($key, 'theme_dimensions'), $element->getLabel());
            $this->assertTrue(
                get_string_manager()->string_exists($key . '_help', 'theme_dimensions'),
                'A help button with no string is a debugging notice on the page.'
            );
        }

        $image = array_search('filemanager:heroimage', $order, true);
        $cover = array_search('filemanager:' . branding::AREA_COVER, $order, true);
        $video = array_search('filemanager:' . branding::AREA_COVERVIDEO, $order, true);
        $crest = array_search('filemanager:' . branding::AREA_BADGES[0], $order, true);
        $this->assertIsInt($image);
        $this->assertIsInt($crest);
        $this->assertGreaterThan($image, $cover, 'The cover follows the hero image it falls back to.');
        $this->assertGreaterThan($cover, $video, 'And the film follows the still it plays over.');
        $this->assertLessThan($crest, $video, 'Both are above the crests, so they stay in the appearance section.');

        // The options, which is where the two differ: an image field and a bounded video field.
        $this->assertSame(['web_image'], branding::cover_options()['accepted_types']);
        $this->assertSame(['.mp4', '.webm'], branding::covervideo_options()['accepted_types']);
        $this->assertSame(
            branding::covervideo_maxbytes(),
            (int) branding::covervideo_options()['maxbytes'],
            'The limit the picker takes is the one the site setting holds.'
        );
        /* The setting really reaches the element, and not only the helper beside it. Core's own
           file manager clamps whatever it is given against the site and PHP upload limits (its
           constructor in lib/form/filemanager.php) - which is the "the site's own limit still
           applies" half the help string promises - so the number to test with is a small one, a
           kilobyte, that no runtime raises. And an admin is exempt from every limit:
           get_user_max_upload_file_size() answers USER_CAN_IGNORE_FILE_SIZE_LIMITS for anybody
           holding ignorefilesizelimits, which is -1 and not a size at all. So the element is
           read as an ordinary person. */
        set_config(branding::SETTING_COVERVIDEO_MAXBYTES, 1024, 'theme_dimensions');
        $this->setUser(self::getDataGenerator()->create_user());
        $rebuilt = self::quickform(new categoryhero(null, ['categoryid' => $categoryid]));
        $options = new \ReflectionProperty(\MoodleQuickForm_filemanager::class, '_options');
        $element = $options->getValue($rebuilt->getElement(branding::AREA_COVERVIDEO));
        $this->assertSame(
            1024,
            (int) $element['maxbytes'],
            'A site that chose another limit gets it on the element itself, not only in the helper.'
        );
        $this->assertSame(
            ['.mp4', '.webm'],
            $element['accepted_types'],
            'And the whole option set reached the element, not just the one value.'
        );
    }

    /**
     * A cover video whose bytes are not a video is refused, on its own field, before it is stored.
     *
     * A file manager never posts a file - it posts a draft area holding one - so the bytes are read
     * out of that area, which is the only place they exist before a save. The real video in the
     * same test is the control: without it this would pass just as well against a form that
     * refuses every upload, which is the state a wrong offset in the signature check produces.
     */
    public function test_a_cover_video_that_is_not_a_video_is_refused_on_its_own_field(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $fixtures = dirname(__DIR__) . '/fixtures/';

        categoryhero::mock_submit(
            [branding::AREA_COVERVIDEO => self::draft_with('capa.mp4', (string) file_get_contents($fixtures . 'cover.mp4'))]
            + self::submission($categoryid)
        );
        $good = new categoryhero(null, ['categoryid' => $categoryid]);
        $this->assertTrue($good->is_validated(), 'Control: a real MP4 is accepted.');

        categoryhero::mock_submit(
            [branding::AREA_COVERVIDEO => self::draft_with('capa.webm', (string) file_get_contents($fixtures . 'cover.webm'))]
            + self::submission($categoryid)
        );
        $webm = new categoryhero(null, ['categoryid' => $categoryid]);
        $this->assertTrue($webm->is_validated(), 'Control: and so is a real WebM.');

        categoryhero::mock_submit(
            [branding::AREA_COVERVIDEO => self::draft_with('capa.mp4', 'not a video at all, it is prose')]
            + self::submission($categoryid)
        );
        $bad = new categoryhero(null, ['categoryid' => $categoryid]);
        $this->assertFalse($bad->is_validated(), 'A file named .mp4 whose bytes are not one is refused.');
        $this->assertNull($bad->get_data());
        $errors = self::quickform($bad)->_errors;
        $this->assertSame(
            get_string('categoryhero_invalidvideo', 'theme_dimensions'),
            $errors[branding::AREA_COVERVIDEO] ?? null,
            'And the message is on the field the manager has to fix, not on the form.'
        );

        // No element submitted at all is not a refusal: it is a save that leaves the area alone.
        categoryhero::mock_submit(self::submission($categoryid));
        $silent = new categoryhero(null, ['categoryid' => $categoryid]);
        $this->assertTrue($silent->is_validated());
    }

    /**
     * A draft area holding one file, the way a submitted file manager hands one over.
     *
     * @param string $filename The file name.
     * @param string $content The bytes.
     * @return int The draft item id.
     */
    private static function draft_with(string $filename, string $content): int {
        global $USER;

        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \core\context\user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
        return $draftitemid;
    }

    /**
     * The category's own description is on this form, in core's editor with core's own options.
     *
     * Everything a category page shows is edited on this one screen. The options are core's,
     * deliberately: the two forms write into the same file area, so a
     * narrower maxfiles or a cleaner noclean here would rewrite what a colleague saved on
     * /course/editcategory.php. This reads core's own form for them rather than restating them.
     */
    public function test_the_category_description_is_edited_with_cores_own_options(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $context = \core\context\coursecat::instance($categoryid);

        require_once($CFG->dirroot . '/course/classes/editcategory_form.php');
        $coreform = new \core_course_editcategory_form(null, [
            'categoryid' => $categoryid,
            'parent' => 0,
            'context' => $context,
            'itemid' => 0,
        ]);
        $this->assertEquals(
            $coreform->get_description_editor_options(),
            categoryhero::description_editor_options($categoryid),
            'The two editors of one file area were given different options.'
        );

        $form = new categoryhero(null, ['categoryid' => $categoryid]);
        $this->assertNotNull(
            self::quickform($form)->getElement('description_editor'),
            'The category description is not on the appearance form.'
        );
    }

    /**
     * A description saved with these options lands in core's own field, with its embedded file.
     *
     * The page is a script and cannot be run from here, so this runs the two calls it makes -
     * core's editor options and core_course_category::update() - and checks what they leave
     * behind: the text under core's placeholder, the file in core's area, and core's own event.
     * The wiring itself is asserted below, out of the page's source, because a test that proved
     * only that core works would stay green with the whole save deleted from the page.
     */
    public function test_a_description_saved_with_these_options_reaches_cores_own_field(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $context = \core\context\coursecat::instance($categoryid);
        $storage = get_file_storage();
        $this->assertSame(
            [],
            $storage->get_area_files($context->id, 'coursecat', 'description', 0, 'filename', false),
            'Precondition: the description area starts empty, so finding a file later means something.'
        );

        // A draft area holding one embedded image, as the editor's own file picker fills it.
        $draftitemid = file_get_unused_draft_itemid();
        $storage->create_file_from_string([
            'contextid' => \core\context\user::instance((int) $USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'crest.png',
        ], 'the bytes do not matter here');

        $sink = $this->redirectEvents();
        \core_course_category::get($categoryid, MUST_EXIST, true)->update(
            (object) [
                'description_editor' => [
                    'text' => '<p>Para quem está logado <img src="@@PLUGINFILE@@/crest.png" alt="Crest"></p>',
                    'format' => FORMAT_HTML,
                    'itemid' => $draftitemid,
                ],
            ],
            categoryhero::description_editor_options($categoryid)
        );
        $events = $sink->get_events();
        $sink->close();

        $stored = \core_course_category::get($categoryid, MUST_EXIST, true);
        $this->assertStringContainsString('Para quem está logado', $stored->description);
        $this->assertStringContainsString(
            '@@PLUGINFILE@@/crest.png',
            $stored->description,
            'The embedded file was not rewritten to core\'s placeholder, so it will not serve.'
        );
        $this->assertSame((int) FORMAT_HTML, (int) $stored->descriptionformat);
        $files = $storage->get_area_files($context->id, 'coursecat', 'description', 0, 'filename', false);
        $this->assertCount(1, $files, 'The draft file did not reach the category\'s own description area.');
        $this->assertSame('crest.png', reset($files)->get_filename());

        $updated = array_values(array_filter(
            $events,
            static fn($event) => $event instanceof \core\event\course_category_updated
        ));
        $this->assertCount(1, $updated, 'Core\'s own update event did not fire.');
        $this->assertSame($categoryid, (int) $updated[0]->objectid);
    }

    /**
     * The editor page saves that description, and prepares it before showing the form.
     *
     * The page is a script: nothing else here can execute it, and without this a whole feature
     * could be deleted from it with every other test in this file still green. So the wiring is
     * read out of the source.
     */
    public function test_the_editor_page_prepares_and_saves_the_category_description(): void {
        $source = file_get_contents(dirname(__DIR__, 2) . '/categoryhero.php');
        $this->assertIsString($source);
        $this->assertMatchesRegularExpression(
            '~\$coursecat->update\(\s*\(object\) \[.description_editor. => \$data->description_editor\],~',
            $source,
            'The page no longer saves the category description through core\'s own API.'
        );
        $this->assertStringContainsString(
            'categoryhero_form::description_editor_options($id)',
            $source,
            'The page saves with options of its own rather than the ones the form declared.'
        );
        $this->assertStringContainsString(
            "file_prepare_standard_editor(",
            $source,
            'The page never prepares the description, so the form opens with an empty one.'
        );
        $this->assertMatchesRegularExpression(
            "~'coursecat',\s+'description',\s+0\s*\);~",
            $source,
            'The page prepares a different file area than the one core saves into.'
        );
    }

    /**
     * An unused id and an id the person may not manage are refused alike; a manager gets the category.
     *
     * A teacher of a course inside the category holds no capability at the category itself. Both of
     * their refusals must be the same class with the same message, or the page tells a guesser which
     * ids exist; the administrator on the same existing id is the control that the refusal is the
     * guard answering, not a lookup that never found the category.
     */
    public function test_an_unused_id_and_a_forbidden_one_are_refused_alike(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id]);
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $unused = (int) $category->id + 1000;

        $this->setAdminUser();
        $this->assertSame((int) $category->id, (int) categoryhero::editable_category((int) $category->id)->id);

        $this->setUser($teacher);
        $messages = [];
        foreach ([(int) $category->id, $unused] as $id) {
            try {
                categoryhero::editable_category($id);
                $this->fail("Category id $id was not refused.");
            } catch (\moodle_exception $e) {
                $this->assertInstanceOf(unavailable_exception::class, $e, "Category id $id.");
                $messages[] = $e->getMessage();
            }
        }
        $this->assertSame($messages[0], $messages[1], 'Both refusals read the same.');
    }

    /**
     * A manager of a hidden category still reaches its editor.
     *
     * The refusal asks the capability at the category's own context; core's hidden flag is not a
     * second gate here, as it is not on core's /course/editcategory.php.
     */
    public function test_a_manager_of_a_hidden_category_reaches_its_editor(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category(['visible' => 0]);
        $manager = $generator->create_user();
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($roleid, $manager->id, \core\context\coursecat::instance((int) $category->id));

        $this->setUser($manager);
        $this->assertSame((int) $category->id, (int) categoryhero::editable_category((int) $category->id)->id);
    }

    /**
     * The page asks the form's refusal before it loads, renders or prints anything.
     *
     * categoryhero.php is a script, which a test cannot run; this reads it. Core's own lookup and
     * require_capability() are what it replaced, and either one back on the page is the existence
     * oracle again.
     */
    public function test_the_page_refuses_through_the_form(): void {
        global $CFG;
        $source = file_get_contents($CFG->dirroot . '/theme/dimensions/categoryhero.php');

        $this->assertStringContainsString('$coursecat = categoryhero_form::editable_category($id);', $source);
        $this->assertStringNotContainsString('require_capability(', $source);
        $this->assertStringNotContainsString('core_course_category::get(', $source);
        $this->assertLessThan(
            strpos($source, '$PAGE->'),
            strpos($source, 'editable_category('),
            'The refusal comes before the page is set up.'
        );
    }
}
