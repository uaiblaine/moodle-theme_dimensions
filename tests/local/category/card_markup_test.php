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
 * Theme Dimensions - Tests for the markup of the showcase card and toolbar
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\output\category_card;

/**
 * Pins the markup rules of the showcase templates that nothing in the pipeline reads.
 *
 * Two rules of the card: the favourite star is the last child of the
 * trailing action cluster, never a sibling of the media band; and a state
 * badge is the design system's own, one class naming a state the stylesheet
 * gives a background and an ink in both colour modes, never a Bootstrap
 * utility. phpcs reads PHP, the Mustache lint reads structure and stylelint
 * reads CSS; none of them knows where an element sits or which half of a
 * colour pair is missing.
 *
 * The card is rendered through the real engine from the template's own
 * example context, so what is parsed is what a browser receives. The file
 * also holds the client modules' hooks over the toolbar and the listing,
 * and the editor's pair preview - the one element of the appearance form
 * carrying a colour, whose accessibility and whose style attribute nothing
 * else reads either.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(category_card::class)]
final class card_markup_test extends \advanced_testcase {
    /**
     * The example context of a template, decoded.
     *
     * @param string $name The template name without the component.
     * @return array
     */
    private function example_context(string $name): array {
        $source = file_get_contents(dirname(__DIR__, 3) . '/templates/' . $name . '.mustache');
        $found = preg_match('/Example context \(json\):\s*(.*?)\n\}\}/s', $source, $matches);
        $this->assertSame(1, $found, "No example context in {$name}.");
        $context = json_decode($matches[1], true);
        $this->assertIsArray($context, "The example context of {$name} is not valid JSON.");
        return $context;
    }

    /**
     * Render a template of this theme from its example context and parse the result.
     *
     * @param string $name The template name without the component.
     * @return \DOMXPath
     */
    private function render(string $name): \DOMXPath {
        return $this->render_context($name, $this->example_context($name));
    }

    /**
     * Render a template of this theme from a context of the test's own and parse the result.
     *
     * A template carries ONE example context, and some of these rules are about what a template
     * does NOT write for a context it was not given - the cover cluster of a card with no film.
     * The example stays the fuller case, so that the mustache lint renders the whole markup.
     *
     * @param string $name The template name without the component.
     * @param array $context The context to render it from.
     * @return \DOMXPath
     */
    private function render_context(string $name, array $context): \DOMXPath {
        global $PAGE;
        $PAGE->set_url('/course/index.php');
        $html = $PAGE->get_renderer('core')->render_from_template(
            'theme_dimensions/' . $name,
            $context
        );
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        return new \DOMXPath($document);
    }

    /**
     * An XPath predicate matching one class token.
     *
     * @param string $class The class name.
     * @return string
     */
    private static function has_class(string $class): string {
        return "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
    }

    /**
     * The star is the last child of the action cluster and no sibling of the media band.
     */
    public function test_the_star_closes_the_action_cluster_and_never_floats_beside_the_media_band(): void {
        $this->resetAfterTest();
        $xpath = $this->render('category_card');

        $stars = $xpath->query('//button[' . self::has_class('dim-card__star') . ']');
        $this->assertSame(1, $stars->length, 'Exactly one star per card.');
        $star = $stars->item(0);

        $cluster = $star->parentNode;
        $this->assertInstanceOf(\DOMElement::class, $cluster);
        $this->assertStringContainsString(
            'dim-card__actions',
            $cluster->getAttribute('class'),
            'The star belongs to the action cluster.'
        );
        $this->assertSame($star, $cluster->lastElementChild, 'The star is the LAST member of the cluster.');

        $summary = $xpath->query('.//summary', $cluster);
        $this->assertSame(1, $summary->length, 'The disclosure sits in the cluster too, before the star.');

        $media = $xpath->query('//div[' . self::has_class('dim-card__media') . ']');
        $this->assertSame(1, $media->length);
        $next = $media->item(0)->nextElementSibling;
        $this->assertNotNull($next);
        $this->assertStringNotContainsString(
            'dim-card__star',
            $next->getAttribute('class'),
            'The star is not a sibling of the media band.'
        );
        $this->assertContains($star->getAttribute('aria-pressed'), ['true', 'false'], 'The star states its pressed state.');
    }

    /**
     * The crests are written in the BADGE ROW, not in the media band, and each one is an image with a name.
     *
     * The card view draws them over the bottom-right corner of the image and the list view puts
     * them in the right-hand cluster at a quarter of the size, from ONE markup: the stylesheet
     * lifts them onto the band with an absolute position, exactly as it does the star. So the
     * element's place in the DOM is a contract between the template and the stylesheet, and a
     * crest list moved into the media band would look right in the card view and land on top of
     * a 56px thumbnail in the list view, which no test that only counted images could see.
     */
    public function test_the_crests_are_written_in_the_badge_row_and_carry_their_own_names(): void {
        $this->resetAfterTest();
        $xpath = $this->render('category_card');
        $context = $this->example_context('category_card');

        $lists = $xpath->query('//ul[' . self::has_class('dim-card__crests') . ']');
        $this->assertSame(1, $lists->length, 'One crest list per card.');
        $list = $lists->item(0);

        $parent = $list->parentNode;
        $this->assertInstanceOf(\DOMElement::class, $parent);
        $this->assertStringContainsString(
            'dim-card__row',
            $parent->getAttribute('class'),
            'The crests belong to the badge row, which is what the list view puts beside the state badge.'
        );
        $this->assertSame(
            0,
            $xpath->query(
                '//div[' . self::has_class('dim-card__media') . ']//ul[' . self::has_class('dim-card__crests') . ']'
            )->length,
            'And NOT inside the media band, whose list-view cell is 56px wide.'
        );
        $this->assertSame('list', $list->getAttribute('role'), 'list-style: none takes the list semantics away.');
        $this->assertNotSame('', $list->getAttribute('aria-label'));

        $images = $xpath->query('.//li[' . self::has_class('dim-card__crest') . ']/img', $list);
        $this->assertGreaterThan(1, count($context['badges']), 'The example context exercises more than one crest.');
        $this->assertSame(count($context['badges']), $images->length);
        foreach ($context['badges'] as $index => $badge) {
            $this->assertSame($badge['url'], $images->item($index)->getAttribute('src'));
            $this->assertSame(
                $badge['alt'],
                $images->item($index)->getAttribute('alt'),
                'A crest says which institution it is: the alternative text is its file name.'
            );
            $this->assertSame('lazy', $images->item($index)->getAttribute('loading'));
        }
    }

    /**
     * Every badge the card class can print names a state the stylesheet dresses in BOTH modes.
     *
     * Read from the class rather than from one rendered example, because a
     * rendered card shows one state at a time and the rule is about all of
     * them. The badge is the design system's own, so the pair it needs is a
     * background token AND an ink token, declared for the light mode and
     * again inside the dark block - a state dressed in one mode only is a
     * badge whose text disappears in the other, which is the defect Bootstrap's
     * own .badge produces and which nothing in the pipeline reads.
     */
    public function test_every_badge_names_a_state_the_stylesheet_dresses_in_both_modes(): void {
        $reflection = new \ReflectionClass(category_card::class);
        $tables = [$reflection->getConstant('BADGES')];
        foreach (['BADGE_HELD', 'BADGE_PUBLIC', 'BADGE_HIDDEN'] as $name) {
            $tables[] = ['x' => $reflection->getConstant($name)];
        }
        [$light, $dark] = $this->badge_token_blocks();
        $seen = 0;
        foreach ($tables as $table) {
            foreach ($table as $state => [$classes]) {
                $seen++;
                $found = preg_match('/^dim-badge dim-badge--([a-z]+)$/', $classes, $matches);
                $this->assertSame(1, $found, "Badge {$state} is not the design system's: {$classes}");
                $name = $matches[1];
                foreach (['bg', 'fg'] as $half) {
                    $token = '--dim-badge-' . $name . '-' . $half;
                    $this->assertContains($token, $light, "The light block declares no {$token}.");
                    $this->assertContains($token, $dark, "The dark block declares no {$token}.");
                }
            }
        }
        $this->assertSame(14, $seen, 'Eleven states, held, public and hidden: fourteen badges.');
    }

    /**
     * Every badge carries a Font Awesome icon that core's own font has, and the rendered pill hides it from readers.
     *
     * Nothing in the pipeline reads an icon name: a misspelt one renders an empty square or nothing at all, and
     * every gate stays green. So each name of the badge table is looked up in core's icon map
     * (lib/bundles/fontawesome/scss/_variables.scss above the public directory, which theme_boost's fontawesome.scss
     * imports since 5.3; its "$fa-icons" list is what the font answers to); the route line's arrow is checked the same
     * way. A made-up name is the control.
     */
    public function test_every_badge_icon_is_one_core_draws_and_is_hidden_from_readers(): void {
        global $CFG;

        $map = file_get_contents($CFG->root . '/lib/bundles/fontawesome/scss/_variables.scss');
        $this->assertNotEmpty($map);
        $exists = static fn(string $name): bool => (bool) preg_match('~^\s*"' . preg_quote($name, '~') . '":\s~m', $map);
        $this->assertFalse($exists('route-to-nowhere'), 'Control: a name core does not have is told apart.');

        $reflection = new \ReflectionClass(category_card::class);
        $tables = [$reflection->getConstant('BADGES')];
        foreach (['BADGE_HELD', 'BADGE_PUBLIC', 'BADGE_HIDDEN'] as $name) {
            $tables[] = ['x' => $reflection->getConstant($name)];
        }
        $icons = [];
        foreach ($tables as $table) {
            foreach ($table as $state => $badge) {
                $this->assertCount(3, $badge, "Badge {$state} names its classes, its label and its icon.");
                $this->assertMatchesRegularExpression('~^fa-[a-z0-9-]+$~', $badge[2], "Badge {$state}'s icon.");
                $icons[] = substr($badge[2], 3);
            }
        }
        $icons[] = 'arrow-right';
        $icons[] = 'calendar-plus';
        foreach ($icons as $icon) {
            $this->assertTrue($exists($icon), "Core's Font Awesome has no icon named {$icon}.");
        }

        $this->resetAfterTest();
        $xpath = $this->render('category_card');
        $pills = $xpath->query('//span[' . self::has_class('dim-card__badge') . ']');
        $this->assertGreaterThan(0, $pills->length);
        foreach ($pills as $pill) {
            $glyphs = $xpath->query('./i', $pill);
            $this->assertSame(1, $glyphs->length, 'One icon per pill.');
            $this->assertSame('true', $glyphs->item(0)->getAttribute('aria-hidden'), 'The sentence carries the state.');
            $this->assertNotSame('', trim($pill->textContent), 'And the sentence is there.');
        }
        $routes = $xpath->query('//p[' . self::has_class('dim-card__route') . ']');
        $this->assertSame(1, $routes->length, 'The example carries a route line.');
        $this->assertSame('true', $xpath->query('./i', $routes->item(0))->item(0)->getAttribute('aria-hidden'));
    }

    /**
     * The opening line is a paragraph of its own in the state area, its icon hidden from readers, and absent without a date.
     *
     * The example context carries a route line and no opening line, which is the control: a card handed no date
     * draws none. The test's own context swaps the route for a date.
     */
    public function test_the_opening_line_sits_in_the_state_area_with_a_hidden_icon(): void {
        $this->resetAfterTest();
        $this->assertSame(0, $this->render('category_card')->query('//p[' . self::has_class('dim-card__opens') . ']')->length);

        $context = $this->example_context('category_card');
        $context['hasroute'] = false;
        $context['routelabel'] = '';
        $context['hasopens'] = true;
        $context['openslabel'] = 'Inscrições abrem em 12/11/2026';
        $xpath = $this->render_context('category_card', $context);
        $lines = $xpath->query('//p[' . self::has_class('dim-card__opens') . ']');
        $this->assertSame(1, $lines->length, 'One opening line.');
        $line = $lines->item(0);
        $this->assertSame('Inscrições abrem em 12/11/2026', trim($line->textContent));
        $this->assertSame('true', $xpath->query('./i', $line)->item(0)->getAttribute('aria-hidden'), 'The sentence carries it.');
        $this->assertSame(
            'dim-card__row',
            $xpath->query('preceding-sibling::div[1]', $line)->item(0)->getAttribute('class'),
            'Right under the pill\'s row, where the route line sits.'
        );
        $this->assertSame(0, $xpath->query('//p[' . self::has_class('dim-card__route') . ']')->length, 'Control: no route.');
    }

    /**
     * The custom properties the stylesheet declares for the badges, light block and dark block.
     *
     * The split is the line the FIRST dark selector opens on: everything before it is the
     * light half, everything from it onwards the dark one. The file holds more than one dark
     * block and this reads them all as one, which is what the check needs; what it cannot
     * survive is a dark block placed ABOVE the light token roots, which would then be read as
     * the light half. Keep the dark blocks at the foot of the file, where they are.
     *
     * @return array Two arrays of property names: the light block's, then the dark block's.
     */
    private function badge_token_blocks(): array {
        $source = file_get_contents(dirname(__DIR__, 3) . '/scss/category.scss');
        $this->assertNotEmpty($source);
        $at = strpos($source, '[data-bs-theme="dark"]');
        $this->assertNotFalse($at, 'The stylesheet declares no dark block at all.');
        $start = (int) strrpos(substr($source, 0, $at), "\n") + 1;
        $light = $this->badge_tokens(substr($source, 0, $start));
        $dark = $this->badge_tokens(substr($source, $start));
        $this->assertNotEmpty($light, 'No badge tokens outside the dark block at all.');
        $this->assertNotEmpty($dark, 'No badge tokens inside the dark block at all.');
        return [$light, $dark];
    }

    /**
     * Every badge custom property a stretch of the stylesheet declares.
     *
     * @param string $source A stretch of the stylesheet.
     * @return string[] The property names, in the order they are declared.
     */
    private function badge_tokens(string $source): array {
        preg_match_all('~(--dim-badge-[a-z]+-(?:bg|fg))\s*:~', $source, $names);
        return $names[1];
    }

    /**
     * A rendered badge carries no Bootstrap badge class and no Bootstrap background utility.
     *
     * Bootstrap 5.3's .badge forces white text and its own measures, which
     * would put near-white text on a light fill.
     */
    public function test_the_rendered_badges_are_the_design_systems_own(): void {
        $this->resetAfterTest();
        $xpath = $this->render('category_card');
        $badges = $xpath->query('//span[' . self::has_class('dim-badge') . ']');
        $this->assertGreaterThan(0, $badges->length, 'The example context renders at least one badge.');
        foreach ($badges as $badge) {
            $classes = $badge->getAttribute('class');
            $this->assertDoesNotMatchRegularExpression(
                '/(?<![\w-])bg-[a-z]+(?![\w-])/',
                $classes,
                'A rendered badge carries a Bootstrap background utility: ' . $classes
            );
            $this->assertNotContains('badge', explode(' ', $classes), 'A rendered badge wears Bootstrap .badge: ' . $classes);
            $this->assertMatchesRegularExpression(
                '/(?<![\w-])dim-badge--[a-z]+(?![\w-])/',
                $classes,
                'A rendered badge names no state: ' . $classes
            );
        }
    }

    /**
     * The toolbar's submit is inside its form, and the form carries the category.
     *
     * The template lint renders the partial alone, so a submit outside the
     * form would fail it; and a form without the category would land the
     * no-JavaScript reader on the site root.
     */
    public function test_the_toolbar_form_holds_its_own_submit_and_the_category(): void {
        $this->resetAfterTest();
        $xpath = $this->render('category_toolbar');
        $forms = $xpath->query('//form[' . self::has_class('dim-toolbar') . ']');
        $this->assertSame(1, $forms->length);
        $form = $forms->item(0);
        $this->assertSame('get', strtolower($form->getAttribute('method')), 'A GET form: the address bar carries the state.');
        $submit = './/button[@type="submit"][' . self::has_class('dim-toolbar__submit') . ']';
        $this->assertSame(1, $xpath->query($submit, $form)->length);
        // Enter in the search box presses the form's FIRST submit: it must carry no name, or the view flips.
        $first = $xpath->query('.//button[@type="submit"]', $form)->item(0);
        $this->assertNotNull($first);
        $this->assertFalse($first->hasAttribute('name'), 'The first submit of the form is name-less.');
        // And it must be the dedicated one: the sort row's own submit is name-less too, so with the default
        // submit deleted "the first one has no name" would still hold.
        $default = $xpath->query('.//button[@type="submit"][' . self::has_class('dim-toolbar__defaultsubmit') . ']', $form);
        $this->assertSame(1, $default->length, 'The form holds its dedicated default submit.');
        $this->assertTrue($first->isSameNode($default->item(0)), 'The dedicated default submit stands before every other.');
        $this->assertSame(1, $xpath->query('.//input[@type="hidden"][@name="categoryid"]', $form)->length);
        $this->assertGreaterThan(0, $xpath->query('.//input[@type="radio"]', $form)->length, 'The chips are radios.');
        foreach ($xpath->query('.//input[@type="radio"]', $form) as $radio) {
            $id = $radio->getAttribute('id');
            $this->assertSame(1, $xpath->query('.//label[@for="' . $id . '"]', $form)->length, "Radio {$id} has its label.");
        }
    }

    /**
     * Every hook the client modules address is a data attribute these templates really write.
     *
     * The modules read nothing from the DOM that they did not put there or that a template
     * documents as a hook, and a class name is not a hook: it is presentation and moves when the
     * design does, silently disarming whatever addressed it.
     *
     * Changes that must make it fail: renaming any one of these attributes in its template.
     */
    public function test_the_client_modules_hooks_are_written_by_these_templates(): void {
        $this->resetAfterTest();

        $card = $this->render('category_card');
        $cardhooks = [
            'dim-card' => '//*[@data-region="dim-card"][@data-courseid][@data-state]',
            'dim-card-details' => '//details[@data-region="dim-card-details"]',
            'dim-card-panel' => '//details[@data-region="dim-card-details"]//*[@data-region="dim-card-panel"]',
            'dim-card-titlelink' => '//a[@data-region="dim-card-titlelink"]',
            // The heading, where the search term is marked, around the link and the plain title alike.
            'dim-card-title' => '//h3[@data-region="dim-card-title"][' . self::has_class('dim-card__title') . ']'
                . '/a[@data-region="dim-card-titlelink"]',
            'dim-card-progress' => '//*[@data-region="dim-card-progress"][@hidden]',
            'dim-star' => '//button[@data-action="dim-star"][@data-star-add][@data-star-remove]',
            'dim-star-label' => '//button[@data-action="dim-star"]//*[@data-region="dim-star-label"]',
            // The course dialogue the card carries, inert, and the two parts categorycoursemodal moves into core/modal.
            'dim-card-modal' => '//article[@data-region="dim-card"]/template[@data-region="dim-card-modal"]',
            'dim-cmodal-sheet' => '//template[@data-region="dim-card-modal"]/*[@data-region="dim-cmodal-sheet"]',
            'dim-cmodal-actions' => '//template[@data-region="dim-card-modal"]/*[@data-region="dim-cmodal-actions"]',
            // Core's own title hook on the cover's title: core/modal's label, and Behat's dialogue selector.
            'title' => '//*[@data-region="dim-cmodal-sheet"]//h2[@data-region="title"]',
        ];
        foreach ($cardhooks as $hook => $query) {
            $this->assertSame(
                1,
                $card->query($query)->length,
                "The card no longer writes the {$hook} hook the client module addresses."
            );
        }

        /* A visitor's card of a public course with no landing page model draws its title as plain text,
           with no link and so no dim-card-titlelink: the heading still names itself, which is why
           the search term is marked there - a hook on the link alone would leave those titles
           unmarked in a visitor's panel. */
        $plain = $this->render_context(
            'category_card',
            ['hasurl' => false, 'url' => null] + $this->example_context('category_card')
        );
        $this->assertSame(
            1,
            $plain->query('//h3[@data-region="dim-card-title"]/span[@title]')->length,
            'A title with no page to open still sits in the heading hook.'
        );
        $this->assertSame(
            0,
            $plain->query('//*[@data-region="dim-card-titlelink"]')->length,
            'And there is no link hook to focus.'
        );

        $toolbar = $this->render('category_toolbar');
        $toolbarhooks = [
            'dim-vitrine-toolbar' => '//form[@data-region="dim-vitrine-toolbar"]',
            'dim-vitrine-group' => '//fieldset[@data-region="dim-vitrine-group"][@data-name]',
            'dim-pill-track' => '//fieldset[@data-region="dim-vitrine-group"]//*[@data-region="dim-pill-track"]',
            'dim-pill-count' => '//*[@data-region="dim-pill-track"]//*[@data-region="dim-pill-count"]',
            'dim-toolbar-notice' => '//*[@data-region="dim-toolbar-notice"]',
            /* The filter badge, pinned inside the summary: that is the part of the filter panel a
               reader who collapsed it still sees. Both halves - the digit and the sentence a screen
               reader is read - carry their own region because the client rewrites both. */
            'dim-filtercount' => '//details[@data-region="dim-filters"]/summary//*[@data-region="dim-filtercount"]',
            'dim-filtercount-number' =>
                '//*[@data-region="dim-filtercount"]//*[@data-region="dim-filtercount-number"]',
            'dim-filtercount-label' =>
                '//*[@data-region="dim-filtercount"]//*[@data-region="dim-filtercount-label"]',
            'dim-vitrine-count' => '//*[@data-region="dim-vitrine-count"][@role="status"]',
            'dim-vitrine-clear' => '//a[@data-region="dim-vitrine-clear"]',
            /* The count paragraph's two parts and the two that follow the chips in the row under
               the line, each rewritten by the client; the clear link's action, shared by every
               "clear filters" of the page; and the view toggle, one group on the line. */
            'dim-vitrine-countnumber' => '//*[@data-region="dim-vitrine-count"]/b[@data-region="dim-vitrine-countnumber"]',
            'dim-vitrine-counttext' => '//*[@data-region="dim-vitrine-count"]/span[@data-region="dim-vitrine-counttext"]',
            'dim-vitrine-seeall' => '//*[@data-region="dim-line-chips"]/span[@data-region="dim-vitrine-seeall"]/a[@href]',
            'dim-vitrine-countnote' => '//*[@data-region="dim-line-chips"]/span[@data-region="dim-vitrine-countnote"][@hidden]',
            'dim-vitrine-countnotetext' => '//*[@data-region="dim-vitrine-countnote"]//*[@data-region="dim-vitrine-countnotetext"]',
            'dim-clear on the clear link' => '//a[@data-region="dim-vitrine-clear"][@data-action="dim-clear"]',
            'dim-view-row' => '//*[@data-region="dim-line"]/*[@data-region="dim-view-row"][@role="group"]',
            /* The filter line and what the clients address on it and under it: the line, the search
               pill and its eraser, the Organise menu, the scope and "More statuses" halves of the
               state group, the row under the line, its list of chips and each chip's action. */
            'dim-line' => '//form[@data-region="dim-vitrine-toolbar"]/div[@data-region="dim-line"]',
            'dim-line-search' => '//*[@data-region="dim-line"]/div[@data-region="dim-line-search"]//input[@type="search"]',
            'dim-search-clear' => '//*[@data-region="dim-line-search"]/button[@type="button"][@data-action="dim-search-clear"]',
            'dim-organise' => '//*[@data-region="dim-line"]/details[@data-region="dim-organise"]/summary',
            'dim-vitrine-group scope' => '//*[@data-region="dim-line"]/fieldset[@data-region="dim-vitrine-group"]'
                . '[@data-name="chip"][@data-role="scope"]//*[@data-region="dim-pill-track"]',
            'dim-vitrine-group more' => '//details[@data-region="dim-filters"]//fieldset[@data-region="dim-vitrine-group"]'
                . '[@data-name="chip"][@data-role="more"]//*[@data-region="dim-pill-track"]',
            'dim-line-chips' => '//form[@data-region="dim-vitrine-toolbar"]/div[@data-region="dim-line-chips"]',
            'dim-line-chiplist' => '//*[@data-region="dim-line-chips"]/*[@data-region="dim-line-chiplist"]',
            'dim-chip-remove' => '//*[@data-region="dim-line-chiplist"]/a[@data-action="dim-chip-remove"][@data-param][@href]',
            /* The favourites toggle on the line, right before the filter disclosure, with
               the action the client intercepts, the number the client rewrites in its name and the
               hidden field the client enables while it is on. */
            'dim-line-star' => '//details[@data-region="dim-filters"]'
                . '/preceding-sibling::*[1][self::button][@data-region="dim-line-star"][@data-action="dim-fav"]',
            'dim-fav-count' => '//button[@data-region="dim-line-star"]//*[@data-region="dim-fav-count"]',
            'dim-fav-field' => '//form//input[@type="hidden"][@name="fav"][@data-region="dim-fav-field"]',
            /* What the filter line's client (categoryline) drives and nothing else draws: the search's
               icon, pointing at the field it opens; the button that closes the search over the line;
               the listbox, hidden, carrying its id and its two headings; the Filters panel and the
               Organise menu it places under the line; and the menu's "Show as", whose buttons carry
               the view toggle's own action, so the clients' view handler serves them. */
            'dim-search-open' => '//*[@data-region="dim-line"]/button[@type="button"][@data-action="dim-search-open"]'
                . '[@aria-controls="dim-vitrine-q-7"][@aria-expanded="false"]',
            'dim-search-close' => '//*[@data-region="dim-line"]/button[@type="button"][@data-action="dim-search-close"]'
                . '[@aria-label]',
            'dim-line-suggest' => '//*[@data-region="dim-line"]/div[@data-region="dim-line-suggest"][@id="dim-vitrine-suggest-7"]'
                . '[@data-label-filters][@data-label-start][@hidden]',
            'dim-filters-panel' => '//details[@data-region="dim-filters"]/div[@data-region="dim-filters-panel"]',
            'dim-organise-menu' => '//details[@data-region="dim-organise"]/div[@data-region="dim-organise-menu"]',
            'dim-showas' => '//*[@data-region="dim-organise-menu"]/*[@data-region="dim-showas"][@role="group"]'
                . '/button[@type="button"][@data-action="dim-view"][@value][@aria-pressed]',
        ];
        foreach ($toolbarhooks as $hook => $query) {
            $this->assertGreaterThan(
                0,
                $toolbar->query($query)->length,
                "The toolbar no longer writes the {$hook} hook the client module addresses."
            );
        }
        /* Both view buttons, because the module addresses them as a pair and swaps aria-pressed:
           ONE group of two named submits, List first, each named by a visually hidden word and a
           tooltip. The phone's copy of the toggle is gone with the rows it swapped between. */
        $this->assertSame(
            2,
            $toolbar->query('//button[@data-action="dim-view"][@name="view"]')->length,
            'The view toggle is two named submits, addressed by data-action.'
        );
        $this->assertSame(0, $toolbar->query('//*[@data-region="dim-view-phone"]')->length, 'There is no phone copy.');
        $views = iterator_to_array(
            $toolbar->query('//*[@data-region="dim-view-row"]/button[@data-action="dim-view"][@name="view"]')
        );
        $this->assertSame(
            ['list', 'cards'],
            array_map(static fn(\DOMElement $button): string => $button->getAttribute('value'), $views),
            'List first, as the filter line draws it.'
        );
        foreach ($views as $button) {
            $this->assertNotSame('', $button->getAttribute('title'), 'An icon button has a tooltip.');
            $this->assertSame(
                1,
                $toolbar->query('./span[' . self::has_class('visually-hidden') . ']', $button)->length,
                'And its word is its accessible name, out of the picture.'
            );
        }
        /* The open panel, carried by two hidden fields the panel module keeps in step with the
           address it rewrites, each by its own region. */
        foreach (['open' => 'dim-open-field', 'psort' => 'dim-psort-field'] as $name => $region) {
            $this->assertSame(
                1,
                $toolbar->query('//form//input[@type="hidden"][@name="' . $name . '"][@data-region="' . $region . '"]')->length
            );
        }
        /* The search box is the one hook of SELECTORS that is a type rather than a data-region,
           so a change of type disarms the module while every data-region assertion above stays
           green. The module reads and clears this field by that selector. */
        $this->assertSame(
            1,
            $toolbar->query('//input[@type="search"][@name="' . criteria::PARAM_SEARCH . '"]')->length,
            'The search box is an input of type search, which is how the client module finds it.'
        );

        $listing = $this->render('category_listing');
        $listinghooks = [
            'dim-vitrine' => '//*[@data-region="dim-vitrine"][@data-vitrine]',
            'dim-vitrine-grid' => '//*[@data-region="dim-vitrine-grid"]',
            'dim-vitrine-empty' => '//*[@data-region="dim-vitrine-empty"]',
            'dim-vitrine-pagerbar' => '//*[@data-region="dim-vitrine-pagerbar"]',
            'dim-loadmore' => '//button[@data-action="dim-loadmore"][@data-label][@data-loading]',
            'dim-vitrine-nextpage' => '//a[@data-region="dim-vitrine-nextpage"]',
            // The navigation tree.
            'dim-vitrine-tree' => '//nav[@data-region="dim-vitrine-tree"]',
            /* The treebox is what the client closes at a phone's width; a tree link (below) is a
               filter the client intercepts, which is why it names its own category. */
            'dim-vitrine-treebox' => '//nav[@data-region="dim-vitrine-tree"]//details[@data-region="dim-vitrine-treebox"]',
            /* The areas section, which names its category; each posters grid, which is not the
               direct grid (the listing's module replaces what it finds under that one with course
               cards); the one panel slot; the status paragraph the panel module rewrites, outside
               every grid so it is never re-created; the hint; and the section of the direct
               courses with the heading its skip link lands on. */
            'dim-areas' => '//section[@data-region="dim-areas"][@data-categoryid]',
            'dim-areas-grid' => '//*[@data-region="dim-areas"]//ul[@data-region="dim-areas-grid"]',
            'dim-panel' => '//*[@data-region="dim-areas-grid"]/li[@data-region="dim-panel"][@id="dim-panel"]',
            'dim-panel-status' => '//*[@data-region="dim-areas"]/p[@data-region="dim-panel-status"][@role="status"]',
            'dim-areas-hint' => '//*[@data-region="dim-areas"]//*[@data-region="dim-areas-hint"]',
            'dim-direct' => '//section[@data-region="dim-direct"]//*[@data-region="dim-vitrine-grid"]',
            'dim-direct-title' => '//section[@data-region="dim-direct"]//h2[@id="dim-direct-title"][@tabindex="-1"]',
            'dim-direct-skip' => '//*[@data-region="dim-areas"]//a[@data-region="dim-direct-skip"][@href="#dim-direct-title"]',
        ];
        foreach ($listinghooks as $hook => $query) {
            $this->assertSame(
                1,
                $listing->query($query)->length,
                "The listing no longer writes the {$hook} hook the client modules address."
            );
        }
        /* Load more and the continue link are driven by either client, the logged-in one and the
           visitor's, so they wear the class either client's mark reveals and never dim-js-only,
           which the logged-in client's mark alone reveals. */
        foreach (['//button[@data-action="dim-loadmore"]', '//a[@data-region="dim-vitrine-nextpage"]'] as $query) {
            $classes = ' ' . $listing->query($query)->item(0)->getAttribute('class') . ' ';
            $this->assertStringContainsString(' dim-live-only ', $classes, $query . ' is not revealed by the visitor\'s client.');
            $this->assertStringNotContainsString(' dim-js-only ', $classes, $query . ' is the logged-in client\'s alone.');
        }
        $filtered = $this->render_context('category_listing', ['hasfilters' => true] + $this->example_context('category_listing'));
        $this->assertSame(
            1,
            $filtered->query('//*[@data-region="dim-vitrine-empty"]//a[@data-action="dim-clear"]')->length,
            'The empty state\'s "clear filters" is one of the page\'s dim-clear controls, which the client intercepts.'
        );
        $treelinks = $listing->query('//nav[@data-region="dim-vitrine-tree"]//a[@data-region="dim-tree-link"][@data-categoryid]');
        $this->assertGreaterThan(
            0,
            $treelinks->length,
            'The tree no longer names the category each of its links filters by.'
        );
        $this->assertSame(
            0,
            $listing->query('//*[@data-region="dim-areas-grid"]//*[@data-region="dim-vitrine-grid"]')->length,
            'The posters grid holds no direct grid: the listing\'s module would paint course cards into it.'
        );
        $this->assertSame(
            0,
            $listing->query('//*[@data-region="dim-accordion"] | //*[@data-action="dim-expandall"]')->length,
            'The accordion and its "expand all" are gone.'
        );

        /* Every poster carries the shell a panel draws before its rows arrive, and whether it opens
           at all: the client composes none of it. */
        $poster = $this->render('category_directory_card');
        $posterhooks = [
            'dim-area' => '//article[@data-region="dim-area"][@id][@data-categoryid][@data-name][@data-titleurl]'
                . '[@data-ctaurl][@data-ctalabel][@data-expandable]',
            'dim-poster-control' => '//article[@data-region="dim-area"]//h3//*[@data-region="dim-poster-control"]',
            'dim-poster-trigger' => '//article[@data-region="dim-area"]//*[@data-region="dim-poster-trigger"]'
                . '[@data-open][@data-close]',
            'dim-cover-media on the card' => '//article[@data-region="dim-area"]/*[@data-region="dim-cover-media"]',
            /* The pill and what the article carries for it, the empty note and the trigger's third
               word, so the client can flip whether the poster opens. */
            'dim-area counts' => '//article[@data-region="dim-area"][@data-count][@data-resultcount]',
            'dim-poster-pill' => '//article[@data-region="dim-area"]//*[@data-region="dim-poster-pill"]'
                . '[starts-with(@id, "dim-area-results-")]',
            'dim-poster-emptynote' => '//*[@data-region="dim-poster-control"]//*[@data-region="dim-poster-emptynote"]',
            'dim-poster-trigger empty word' => '//*[@data-region="dim-poster-trigger"][@data-empty]',
            'aria-describedby' => '//*[@data-region="dim-poster-control"][starts-with(@aria-describedby, "dim-area-results-")]',
        ];
        foreach ($posterhooks as $hook => $query) {
            $this->assertSame(
                1,
                $poster->query($query)->length,
                "The poster no longer writes the {$hook} hook the panel module addresses."
            );
        }

        /* The areas' own: what the section says about itself, the summary and its two sentences,
           the "nothing matches" box and its dim-clear, the reveal's summary and the skip link,
           which is written on every category page. */
        $areas = $this->render('category_areas');
        $areahooks = [
            'dim-areas result mode' => '//section[@data-region="dim-areas"][@data-filtered][@data-elsewhere]',
            'dim-areas-nomatch' => '//details[@data-region="dim-areas-nomatch"][@id="dim-nomatch"]'
                . '[@data-label-all][@data-label-less]',
            'dim-nomatch-summary' => '//*[@data-region="dim-areas-nomatch"]/summary[@data-region="dim-nomatch-summary"]',
            'dim-nomatch-list' => '//*[@data-region="dim-areas-nomatch"]/ul[@data-region="dim-nomatch-list"]',
            'dim-areas-empty' => '//*[@data-region="dim-areas-empty"]//a[@data-action="dim-clear"]',
            'dim-areas-moresummary' => '//details[@data-region="dim-areas-more"]/summary[@data-region="dim-areas-moresummary"]',
            'dim-direct-skip' => '//a[@data-region="dim-direct-skip"]',
        ];
        foreach ($areahooks as $hook => $query) {
            $this->assertSame(1, $areas->query($query)->length, "The areas no longer write the {$hook} hook the client addresses.");
        }
        $this->assertGreaterThan(
            0,
            $areas->query('//*[@data-region="dim-nomatch-list"]/li[@data-categoryid]')->length,
            'Each entry of the summary names its area.'
        );

        /* The panel's own, in the no-JavaScript copy the page draws and in the client's copy: the
           region and its labelled heading, the regions the client transplants while the header
           stays, the focus keys it restores the focus by, the rail's two arrows and its tile. */
        $panel = $this->render('category_panel');
        $panelhooks = [
            'dim-panel-section' => '//section[@data-region="dim-panel-section"][@role="region"]'
                . '[@aria-labelledby="dim-panel-title"]',
            'dim-panel-title' => '//h3[@data-region="dim-panel-title"][@id="dim-panel-title"][@tabindex="-1"]'
                . '[@aria-describedby="dim-panel-sub"][@data-focuskey="title"]',
            'dim-panel-subtitle' => '//p[@data-region="dim-panel-subtitle"][@id="dim-panel-sub"]',
            'dim-panel-sort' => '//*[@data-region="dim-panel-sort"][@role="group"]',
            'dim-panel-close' => '//a[@data-action="dim-panel-close"][@data-focuskey="close"]',
            'dim-panel-also' => '//nav[@data-region="dim-panel-also"]',
            'dim-panel-body' => '//*[@data-region="dim-panel-body"]',
            'dim-panel-rail' => '//*[@data-region="dim-panel-body"]//ul[@data-region="dim-panel-rail"]',
            'dim-panel-prev' => '//button[@data-action="dim-panel-prev"][@aria-hidden="true"][@tabindex="-1"]',
            'dim-panel-next' => '//button[@data-action="dim-panel-next"][@aria-hidden="true"][@tabindex="-1"]',
            'dim-panel-tile' => '//ul[@data-region="dim-panel-rail"]//a[@data-region="dim-panel-tile"][@tabindex="-1"]',
            'dim-panel-foot' => '//*[@data-region="dim-panel-foot"]',
            'dim-panel-counter' => '//*[@data-region="dim-panel-foot"]//*[@data-region="dim-panel-counter"][@aria-hidden="true"]',
            'dim-panel-cta' => '//*[@data-region="dim-panel-foot"]//a[@data-region="dim-panel-cta"][@data-focuskey="cta"]',
        ];
        foreach ($panelhooks as $hook => $query) {
            $this->assertSame(
                1,
                $panel->query($query)->length,
                "The panel no longer writes the {$hook} hook the panel module addresses."
            );
        }
        $this->assertSame(
            2,
            $panel->query(
                '//*[@data-region="dim-panel-sort"]/a[@data-action="dim-panel-sort"][@data-psort][@data-focuskey]'
            )->length,
            'The two orders, each naming its order and its focus key.'
        );
        $client = $this->render_context('category_panel', ['jsmode' => true] + $this->example_context('category_panel'));
        $this->assertSame(
            2,
            $client->query(
                '//*[@data-region="dim-panel-sort"][@role="radiogroup"]/button[@role="radio"][@data-psort][@aria-checked]'
            )->length,
            'In the client\'s copy the orders are a radio group of buttons.'
        );
        $this->assertSame(
            1,
            $client->query('//button[@data-action="dim-panel-close"][@data-focuskey="close"][@aria-label]')->length,
            'And Close is a button.'
        );
        /* The zero state's suggestions: a link in the page's copy and a button in the client's,
           each naming its action, its area and its focus key. */
        $zerostate = [
            'isrows' => false,
            'hascards' => false,
            'iszero' => true,
            'zero' => 'Nenhum resultado.',
            'hassuggestions' => true,
            'suggestions' => [['id' => 9, 'name' => 'Transito', 'count' => '2', 'url' => 'https://example.com/?open=9#dim-panel']],
        ] + $this->example_context('category_panel');
        foreach (['a' => false, 'button' => true] as $element => $jsmode) {
            $zero = $this->render_context('category_panel', ['jsmode' => $jsmode] + $zerostate);
            $this->assertSame(
                1,
                $zero->query(
                    '//*[@data-region="dim-panel-body"]//*[@data-region="dim-panel-suggestions"]/' . $element
                        . '[@data-action="dim-panel-openarea"][@data-categoryid="9"][@data-focuskey="suggest-9"]'
                )->length,
                'The suggestion is a ' . $element . ' carrying the action, the area and the focus key.'
            );
        }
        $error = $this->render_context(
            'category_panel',
            ['iserror' => true, 'isrows' => false, 'hascards' => false] + $this->example_context('category_panel')
        );
        $this->assertSame(
            1,
            $error->query(
                '//*[@data-region="dim-panel-body"]//button[@data-action="dim-panel-retry"][@data-focuskey="retry"]'
            )->length,
            'The error state offers the retry the module listens for.'
        );

        /* The toolbar's own regions. The date fieldset is inside the filter disclosure, which is
           where every control that narrows the listing lives. */
        $this->assertSame(
            1,
            $toolbar->query('//fieldset[@data-region="dim-sort"][@data-name]')->length,
            'The organisation group.'
        );
        $this->assertSame(
            1,
            $toolbar->query('//details[@data-region="dim-filters"]//fieldset[@data-region="dim-date"]')->length,
            'The date fieldset, inside the filter disclosure.'
        );
        /* The calendar's three hooks are data and not markup: its mount point is empty, because a
           page without JavaScript shows the two native inputs and a skeleton drawn here would be a
           control that never becomes one. */
        $mount = $toolbar->query('//fieldset[@data-region="dim-date"]//*[@data-region="dim-daterange"]');
        $this->assertSame(1, $mount->length, 'The calendar mounts into a region of its own.');
        $this->assertSame('', trim($mount->item(0)->textContent), 'And that region is empty without the client.');
        $this->assertSame(
            1,
            $toolbar->query('//fieldset[@data-region="dim-date"][@data-firstday]')->length,
            'The site\'s first weekday travels on the fieldset: the client does no calendar configuration.'
        );
        $this->assertSame(
            1,
            $toolbar->query('//fieldset[@data-region="dim-date"][@data-today]')->length,
            'And so does the day the SITE calls today, which is the other fact the client may not compute.'
        );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}$/',
            $toolbar->query('//fieldset[@data-region="dim-date"]')->item(0)->getAttribute('data-today'),
            'It is a date and not a timestamp: the client compares it with the bounds of the quick options.'
        );
        $quick = $toolbar->query('//a[' . self::has_class('dim-quick') . ']');
        $this->assertGreaterThan(0, $quick->length, 'The quick options are still ordinary links.');
        foreach ($quick as $link) {
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}$/',
                $link->getAttribute('data-from'),
                'Each quick option carries its own bounds, so the calendar reuses them and computes none.'
            );
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $link->getAttribute('data-to'));
            $this->assertSame(
                'dim-quick',
                $link->getAttribute('data-action'),
                'The client intercepts the press by that attribute, so the link stays an address as well.'
            );
        }
        /* Two regions the client writes into: the pair of native fields the calendar hides behind
           itself, and the removable period pill of the count row, which the server leaves empty -
           the one place a reader who shut the filter panel can still see, and drop, the period. */
        $fields = $toolbar->query('//*[@data-region="dim-dateinputs"]');
        $this->assertSame(1, $fields->length, 'The two native fields are one region, so one write hides both.');
        $this->assertSame(
            2,
            $toolbar->query('//*[@data-region="dim-dateinputs"]//input[@type="date"]')->length,
            'And they are still there without the client, which is the page a reader without it gets.'
        );
        $this->assertSame(
            2,
            $toolbar->query('//*[@data-region="dim-dateinputs"]/*[' . self::has_class('dim-toolbar__datefield') . ']')->length,
            'Each label and its field are ONE item of that row, so the pair wraps as a unit.'
        );
        $pill = $toolbar->query('//*[@data-region="dim-line-chips"]/*[@data-region="dim-daterange-pill"]');
        $this->assertSame(1, $pill->length, 'The period\'s chip has its region in the row under the line.');
        $this->assertSame(
            1,
            $toolbar->query('./a[@data-action="dim-chip-remove"][@data-param="period"]', $pill->item(0))->length,
            'And the server draws the period\'s chip in it: the calendar draws none of its own.'
        );

        /* The covervideo module, whose SELECTORS are all eight of these. It addresses nothing by
           class name, so this is the whole surface between the template and the client: the band
           it marks as playing, the film it plays, the cluster Escape is scoped to, the bar it draws
           into, the glyph each toggle swaps, and the three actions. The poster is the only
           template that writes them, and the band it marks is the poster's whole card rather than
           its media; every listener of that module is on the document, so a poster the page draws
           after it ran is served like any other. */
        $directory = $this->render('category_directory_card');
        $coverhooks = [
            'dim-cover-media' => '//*[@data-region="dim-cover-media"]',
            'dim-cover-video' => '//*[@data-region="dim-cover-media"]//video[@data-region="dim-cover-video"]',
            'dim-cover-controls' => '//*[@data-region="dim-cover-controls"]',
            'dim-cover-bar' => '//*[@data-region="dim-cover-progress"]//*[@data-region="dim-cover-bar"]',
            'dim-cover-play' => '//button[@data-action="dim-cover-play"]',
            'dim-cover-mute' => '//button[@data-action="dim-cover-mute"]',
            'dim-cover-fullscreen' => '//button[@data-action="dim-cover-fullscreen"]',
        ];
        foreach ($coverhooks as $hook => $query) {
            $this->assertSame(
                1,
                $directory->query($query)->length,
                "The directory card no longer writes the {$hook} hook the cover module addresses."
            );
        }
        /* One glyph inside each of the three controls: the two toggles swap theirs, and a button
           whose icon the module cannot find keeps saying "play" over a film that is running. */
        $this->assertSame(
            3,
            $directory->query('//*[@data-region="dim-cover-controls"]//*[@data-region="dim-cover-glyph"]')->length,
            'Each control carries the glyph hook the module swaps.'
        );
    }

    /**
     * The course dialogue is core/modal's frame with a header of the close button, and its parts name nothing twice.
     *
     * The frame keeps core's container, dialog, body and footer hooks (core/modal finds them by those), its close
     * button and a hidden title carrying the id the root is labelled by, which the module hands to the cover's
     * title. The parts carry no id at all - a page draws one per card - their icons are hidden from readers, and a
     * later opening is a button that is aria-disabled, never disabled, so it stays in the focus order.
     */
    public function test_the_course_dialogue_is_core_modal_and_its_closed_button_stays_reachable(): void {
        $this->resetAfterTest();

        $frame = $this->render('category_course_modal');
        $root = $frame->query('//div[@data-region="modal-container"][@role="dialog"][@aria-labelledby]');
        $this->assertSame(1, $root->length, 'core/modal\'s own root.');
        $label = $root->item(0)->getAttribute('aria-labelledby');
        $this->assertSame(
            1,
            $frame->query('//div[@data-region="header"]//h2[@id="' . $label . '"][@data-region="title"]['
                . self::has_class('visually-hidden') . ']')->length,
            'The header keeps core\'s title region: core/modal raises an exception without one.'
        );
        $this->assertSame(
            1,
            $frame->query('//div[@data-region="modal"][' . self::has_class('dim-cmodal__dialog') . ']')->length
        );
        $this->assertSame(
            1,
            $frame->query('//div[@data-region="header"][' . self::has_class('dim-cmodal__close') . ']'
                . '/button[@data-action="hide"]')->length,
            'The header keeps core\'s close button.'
        );
        $this->assertSame(1, $frame->query('//div[@data-region="body"]//*[@data-region="title"]')->length);
        $this->assertSame(1, $frame->query('//div[@data-region="footer"]//a[@href]')->length);

        $parts = $this->render('category_card_modal');
        $this->assertSame(0, $parts->query('//*[@id]')->length, 'No id in the parts: a page draws one per card.');
        $this->assertGreaterThan(0, $parts->query('//i')->length);
        foreach ($parts->query('//i') as $icon) {
            $this->assertSame('true', $icon->getAttribute('aria-hidden'), 'Every icon is decoration.');
        }
        $this->assertSame(3, $parts->query('//ul[@aria-label]/li[' . self::has_class('dim-cmodal__fact') . ']')->length);
        $this->assertSame(
            1,
            $parts->query('//a[' . self::has_class('dim-cmodal__button--secondary') . '][@href]')->length
        );
        $this->assertSame(1, $parts->query('//a[' . self::has_class('dim-cmodal__button--primary') . '][@href]')->length);
        $this->assertSame(0, $parts->query('//button')->length, 'Control: an open course draws no closed button.');

        $context = $this->example_context('category_card_modal');
        $context['modal']['primary'] = [
            'label' => 'Inscrições a partir de 14/11/2026',
            'url' => null,
            'disabled' => true,
            'note' => 'As inscrições deste curso ainda não abriram.',
            'icon' => 'fa-calendar',
        ];
        $closed = $this->render_context('category_card_modal', $context);
        $buttons = $closed->query('//button[' . self::has_class('dim-cmodal__button--closed') . ']');
        $this->assertSame(1, $buttons->length);
        $button = $buttons->item(0);
        $this->assertSame('true', $button->getAttribute('aria-disabled'));
        $this->assertFalse($button->hasAttribute('disabled'), 'A disabled button would leave the focus order.');
        $this->assertSame('dim-cmodal-closed', $button->getAttribute('data-region'));
        $this->assertStringContainsString('As inscrições deste curso ainda não abriram.', $button->textContent);
        $this->assertSame(1, substr_count($closed->document->textContent, '14/11/2026'), 'The date, once.');
        $this->assertSame(0, $closed->query('//a[' . self::has_class('dim-cmodal__button--primary') . ']')->length);
    }

    /**
     * The favourites toggle is the card's own star on the filter line, and no pill of the state group wears one.
     *
     * The favourites combine with the state rather than being one of its values, so the toggle is a
     * submit button of its own beside the Filter control: aria-pressed says whether it is on, the
     * glyph is the very star the course card's favourite button draws (solid when pressed), and the
     * word and the number are its accessible name, hidden from the eye, with the word as its tooltip.
     */
    public function test_the_favourites_toggle_is_the_cards_star_beside_the_filter_control(): void {
        $this->resetAfterTest();
        $toolbar = $this->render('category_toolbar');
        $stars = $toolbar->query('//button[@data-region="dim-line-star"]');
        $this->assertSame(1, $stars->length, 'One toggle.');
        $star = $stars->item(0);
        $this->assertSame('submit', $star->getAttribute('type'));
        $this->assertSame('fav', $star->getAttribute('name'));
        $this->assertSame('1', $star->getAttribute('value'), 'Released, a press turns it on.');
        $this->assertSame('false', $star->getAttribute('aria-pressed'));
        $this->assertSame('Favourites', $star->getAttribute('title'), 'Its tooltip names it.');
        $glyph = $toolbar->query('./i[' . self::has_class('fa-star') . ']', $star)->item(0);
        $this->assertNotNull($glyph);
        $this->assertStringContainsString('fa-regular', $glyph->getAttribute('class'), 'The card\'s released star.');
        $this->assertSame('true', $glyph->getAttribute('aria-hidden'), 'Decorative: the name is the words beside it.');
        $this->assertSame('Favourites 2', trim(preg_replace('/\s+/', ' ', $star->textContent)), 'The word and the number.');
        $this->assertSame(
            1,
            $toolbar->query('./span[' . self::has_class('visually-hidden') . ']//*[@data-region="dim-fav-count"]', $star)->length,
            'The number is in the name, out of the picture.'
        );
        $field = $toolbar->query('//input[@data-region="dim-fav-field"]')->item(0);
        $this->assertTrue($field->hasAttribute('disabled'), 'Released, the hidden field carries nothing.');
        $this->assertSame(
            1,
            $toolbar->query(
                '//*[@data-region="dim-line-search"]/following-sibling::*[not(@data-action="dim-search-open")][1]'
                    . '[@data-region="dim-line-star"]'
            )->length,
            'The star follows the search (and the icon the client folds it into) on the line, as the contract orders the slots.'
        );

        $group = $toolbar->query('//fieldset[@data-region="dim-vitrine-group"][@data-name="chip"]')->item(0);
        $this->assertNotNull($group, 'Precondition: the state group is drawn.');
        $this->assertSame(
            0,
            $toolbar->query('.//i[' . self::has_class('fa-star') . ']', $group)->length,
            'No star in the state group.'
        );
        $this->assertSame(0, $toolbar->query('.//input[@value="favourites"]', $group)->length, 'And no favourites pill.');

        $context = $this->example_context('category_toolbar');
        $context['favourites'] = ['pressed' => true, 'value' => 0] + $context['favourites'];
        $pressed = $this->render_context('category_toolbar', $context);
        $star = $pressed->query('//button[@data-region="dim-line-star"]')->item(0);
        $this->assertSame('true', $star->getAttribute('aria-pressed'));
        $this->assertSame('0', $star->getAttribute('value'), 'Pressed, a press turns it off.');
        $this->assertSame(
            1,
            $pressed->query('./i[' . self::has_class('fa-solid') . '][' . self::has_class('fa-star') . ']', $star)->length,
            'The card\'s pressed star.'
        );
        $field = $pressed->query('//input[@data-region="dim-fav-field"]')->item(0);
        $this->assertFalse($field->hasAttribute('disabled'), 'Pressed, the hidden field carries the toggle.');

        $card = $this->render_context('category_card', ['favourite' => true] + $this->example_context('category_card'));
        $this->assertSame(
            1,
            $card->query(
                '//button[@data-action="dim-star"]//i[' . self::has_class('fa-solid') . '][' . self::has_class('fa-star') . ']'
            )->length,
            'The control: the card\'s pressed star is the same two classes.'
        );

        $context['favourites'] = null;
        $context['hasfavourites'] = false;
        $none = $this->render_context('category_toolbar', $context);
        $this->assertSame(0, $none->query('//*[@data-region="dim-line-star"]')->length, 'No toggle where none is offered.');
        $this->assertSame(0, $none->query('//input[@name="fav"]')->length, 'And no field to carry one.');
        $this->assertSame(0, $none->query('//form[' . self::has_class('dim-toolbar--hasstar') . ']')->length, 'Nor room for one.');
    }

    /**
     * The favourites toggle works with JavaScript off: what a GET submission of the form carries.
     *
     * The query string is built as a browser builds it - every enabled control in document order,
     * and of the submit buttons only the one pressed - and read as PHP reads it, where the last of
     * two values of one name wins. Pressing the released toggle asks for the favourites; pressing the
     * pressed one asks for them off although the hidden field still says on, because the button
     * comes later; and any other submission keeps what the toggle says, which is the hidden field's
     * whole job - the control without it loses the favourites on Enter in the search box.
     */
    public function test_the_favourites_toggle_posts_without_javascript(): void {
        $this->resetAfterTest();
        $context = $this->example_context('category_toolbar');
        $released = $this->render_context('category_toolbar', $context);
        $this->assertSame('1', $this->submitted($released, '[@data-region="dim-line-star"]')['fav'] ?? null);
        $this->assertArrayNotHasKey('fav', $this->submitted($released, '[' . self::has_class('dim-toolbar__defaultsubmit') . ']'));

        $context['favourites'] = ['pressed' => true, 'value' => 0] + $context['favourites'];
        $pressed = $this->render_context('category_toolbar', $context);
        $this->assertSame('0', $this->submitted($pressed, '[@data-region="dim-line-star"]')['fav'] ?? null);
        $enter = $this->submitted($pressed, '[' . self::has_class('dim-toolbar__defaultsubmit') . ']');
        $this->assertSame('1', $enter['fav'] ?? null, 'Enter in the search box keeps the favourites on.');
        $this->assertSame('rescue', $enter['q'] ?? null, 'Precondition: the form really was read.');
        $view = $this->submitted($pressed, '[@data-region="dim-view-row"]/button[@value="list"]', true);
        $this->assertSame('1', $view['fav'] ?? null, 'And so does the view toggle.');

        $field = $pressed->query('//input[@data-region="dim-fav-field"]')->item(0);
        $field->parentNode->removeChild($field);
        $this->assertArrayNotHasKey(
            'fav',
            $this->submitted($pressed, '[' . self::has_class('dim-toolbar__defaultsubmit') . ']'),
            'The control: without the hidden field the favourites are lost.'
        );
    }

    /**
     * What a GET submission of the toolbar form carries, read as PHP reads a query string.
     *
     * @param \DOMXPath $xpath The rendered toolbar.
     * @param string $submitter An XPath predicate (or a step, with $descendant) naming the submit button pressed.
     * @param bool $descendant Whether $submitter is a path below an element rather than a predicate on the button.
     * @return array name => value, the last value of a repeated name winning.
     */
    private function submitted(\DOMXPath $xpath, string $submitter, bool $descendant = false): array {
        $form = $xpath->query('//form[@data-region="dim-vitrine-toolbar"]')->item(0);
        $pressed = $descendant
            ? $xpath->query('.//*' . $submitter, $form)->item(0)
            : $xpath->query('.//button' . $submitter, $form)->item(0);
        $this->assertNotNull($pressed, 'Precondition: the submit button exists.');
        $pairs = [];
        foreach ($xpath->query('.//input|.//button', $form) as $control) {
            if ($control->hasAttribute('disabled') || !$control->hasAttribute('name')) {
                continue;
            }
            $type = strtolower($control->getAttribute('type'));
            if ($control->nodeName === 'button' || $type === 'submit') {
                if (!$control->isSameNode($pressed)) {
                    continue;
                }
            } else if (in_array($type, ['radio', 'checkbox'], true) && !$control->hasAttribute('checked')) {
                continue;
            }
            $pairs[] = rawurlencode($control->getAttribute('name')) . '=' . rawurlencode($control->getAttribute('value'));
        }
        parse_str(implode('&', $pairs), $parsed);
        return $parsed;
    }

    /**
     * The calendar's own template writes every hook its module reads, and names itself.
     *
     * This template is rendered by the client and by nothing else, so the mustache lint is the
     * only other thing that ever renders it and it reads structure rather than attributes. A
     * region renamed here disarms the module in silence: a box stops opening, or the month grids
     * are written into nothing, on a page where every server-side test is still green.
     *
     * Two boxes, each a button named by its label and its date and each with its own clearing
     * control, and ONE popover both of them control: a start alone, an end alone and a range are
     * all periods, and which bound a day sets is the box that opened the calendar. The popover
     * holds two months, the sentence saying which bound the next press sets, and the footer that
     * ends the choice.
     */
    public function test_the_calendars_own_template_writes_every_hook_its_module_reads(): void {
        $this->resetAfterTest();

        $context = $this->example_context('category_daterange');
        $this->assertNotEmpty($context, 'An empty example context renders nothing and proves nothing.');
        $range = $this->render('category_daterange');
        $popid = $range->query('//*[@data-region="dim-daterange-pop"]')->item(0)->getAttribute('id');
        $this->assertSame(1, $range->query('//*[@data-region="dim-daterange-pop"]')->length, 'ONE popover.');
        $this->assertSame(
            1,
            $range->query('//*[@data-region="dim-daterange-pop"][@hidden]')->length,
            'The popover is closed until a box is pressed.'
        );

        $boxes = $range->query('//button[@data-action="dim-daterange-open"]');
        $this->assertSame(2, $boxes->length, 'A start box and an end box.');
        $labels = ['from' => 'category_datefrom', 'to' => 'category_dateto'];
        $clears = ['from' => 'category_dateclearfrom', 'to' => 'category_dateclearto'];
        foreach ($boxes as $index => $box) {
            $bound = $box->getAttribute('data-bound');
            $this->assertSame(['from', 'to'][$index], $bound, 'The start comes first, each named as its parameter.');
            $this->assertSame($popid, $box->getAttribute('aria-controls'), 'Both boxes open the SAME popover.');
            $this->assertSame('false', $box->getAttribute('aria-expanded'), 'Each starts closed and says so.');
            $this->assertNotSame('', $box->getAttribute('id'));
            $label = $range->query('.//*[@data-region="dim-daterange-label"]', $box);
            $this->assertSame(1, $label->length, 'A box carries its label inside its own name.');
            $this->assertSame(get_string($labels[$bound], 'theme_dimensions'), trim($label->item(0)->textContent));
            $this->assertSame(1, $range->query('.//*[@data-region="dim-daterange-value"]', $box)->length);
            $clear = $range->query('//button[@data-action="dim-daterange-clear"][@data-bound="' . $bound . '"]');
            $this->assertSame(1, $clear->length, 'Each box has its own clearing control.');
            $this->assertTrue($clear->item(0)->parentNode->isSameNode($box->parentNode), 'Beside its box.');
            $this->assertSame(
                get_string($clears[$bound], 'theme_dimensions'),
                trim($clear->item(0)->textContent),
                'An icon-only control named for the bound it drops.'
            );
        }
        /* The example sets the start alone: its label leaves the picture and stays in its name, and
           only its clearing control shows. The end box is the control of both. */
        $hiddenlabel = '//*[@data-region="dim-daterange-label"][contains(@class, "visually-hidden")]';
        $this->assertSame(1, $range->query('//button[@data-bound="from"]' . $hiddenlabel)->length);
        $this->assertSame(0, $range->query('//button[@data-bound="to"]' . $hiddenlabel)->length);
        $this->assertSame(
            ['from' => false, 'to' => true],
            [
                'from' => $range->query('//button[@data-action="dim-daterange-clear"][@data-bound="from"]')
                    ->item(0)->hasAttribute('hidden'),
                'to' => $range->query('//button[@data-action="dim-daterange-clear"][@data-bound="to"]')
                    ->item(0)->hasAttribute('hidden'),
            ]
        );

        // Two months, each a caption with an id and an empty grid: the days are the client's.
        $blocks = $range->query('//*[@data-region="dim-daterange-pop"]//*[@data-region="dim-daterange-monthblock"]');
        $this->assertSame(2, $blocks->length, 'The popover holds two months.');
        foreach ($blocks as $block) {
            $caption = $range->query('.//*[@data-region="dim-daterange-month"]', $block);
            $this->assertSame(1, $caption->length);
            $this->assertNotSame('', $caption->item(0)->getAttribute('id'), 'The grid is labelled by its caption\'s id.');
            $grid = $range->query('.//*[@data-region="dim-daterange-grid"]', $block);
            $this->assertSame(1, $grid->length);
            $this->assertSame(
                '',
                trim($grid->item(0)->textContent),
                'The grid is empty here: its days, their names and the column a week begins under are the client\'s.'
            );
        }
        $this->assertSame(
            1,
            $range->query('//*[@data-region="dim-daterange-pop"]//*[@data-region="dim-daterange-prompt"][@aria-live="polite"]')
                ->length,
            'The sentence saying which bound the next press sets is read when it changes.'
        );
        foreach (['dim-daterange-prev', 'dim-daterange-next', 'dim-daterange-done'] as $action) {
            $this->assertSame(
                1,
                $range->query('//*[@data-region="dim-daterange-pop"]//button[@data-action="' . $action . '"]')->length,
                'The ' . $action . ' control.'
            );
        }
        /* The footer. "Done" applies what the presses described, and it lives inside the popover,
           because a control that ends a choice has to be where the choice is being made. Beside it
           is the control that drops the whole period: the same action as each box's own, with no
           bound, so one handler serves all three. */
        $this->assertSame(
            1,
            $range->query('//*[@data-region="dim-daterange-pop"]//button[@data-action="dim-daterange-clear"][not(@data-bound)]')
                ->length,
            'The footer\'s control drops the whole period.'
        );
        // Every one of them names itself for a screen reader, icon-only or not.
        foreach ($range->query('//button[starts-with(@data-action, "dim-daterange-")]') as $button) {
            $this->assertNotSame(
                '',
                trim($button->textContent),
                'An icon-only control with no name is a control a screen reader cannot announce.'
            );
        }
    }

    /**
     * A poster says it is a category wherever it is drawn: the open grid, the reveal, the root.
     *
     * The tag is written by the poster partial, which is what makes it true on every surface a
     * poster is drawn: the areas' open grid, the reveal past the twenty-fourth, and so the root's
     * directory and a category page alike. The label is a literal of the template rather than a
     * field of the context, so the surfaces cannot disagree about it. The course card beside it
     * is the control: its own tag is data, because a course may be a course or an event, and a
     * test that only looked at the poster could pass against a template that had lost the
     * distinction altogether. The tag is text alone, with no glyph.
     */
    public function test_a_category_card_says_it_is_a_category_wherever_it_is_drawn(): void {
        $this->resetAfterTest();
        $expected = get_string('category_kind_category', 'theme_dimensions');

        // 1. The partial on its own.
        $poster = $this->render('category_directory_card');
        $tags = $poster->query('//*[' . self::has_class('dim-poster__top') . ']//*[' . self::has_class('dim-poster__kind') . ']');
        $this->assertSame(1, $tags->length, 'The tag is in the poster\'s top row, where the course card carries its own.');
        $this->assertSame($expected, trim($tags->item(0)->textContent), 'And it reads as the category it is.');
        $this->assertSame(0, $tags->item(0)->getElementsByTagName('i')->length, 'Text alone, no glyph.');

        /* 2 and 3. The areas: the open grid and the reveal below it. One tag per poster in each,
           counted against the posters themselves, so a partial that drew the tag once for the page
           rather than once per poster fails here. */
        $areas = $this->render('category_areas');
        $context = $this->example_context('category_areas');
        $this->assertNotEmpty($context['posters'], 'The example must draw posters in the open grid.');
        $this->assertNotEmpty($context['postersmore'], 'And posters inside the reveal, or surface three is not rendered.');
        $surfaces = [
            'the open grid' => '//*[@data-region="dim-areas"]/ul[@data-region="dim-areas-grid"]',
            'the reveal' => '//details[@data-region="dim-areas-more"]',
        ];
        foreach ($surfaces as $where => $scope) {
            $posters = $areas->query($scope . '//article[@data-region="dim-area"]');
            $this->assertGreaterThan(0, $posters->length, 'The example draws no poster in ' . $where . '.');
            $this->assertSame(
                $posters->length,
                $areas->query(
                    $scope . '//article[@data-region="dim-area"]//*[' . self::has_class('dim-poster__kind') . ']'
                )->length,
                'One tag per poster in ' . $where . ', and no poster without one.'
            );
        }
        $this->assertSame(
            count($this->example_context('category_directory')['areas']['posters']),
            $this->render('category_directory')->query('//*[@data-region="dim-areas"]//article[@data-region="dim-area"]')->length,
            'The root\'s directory draws the same posters, through the same partial.'
        );

        /* The control: a COURSE card's tag is the context's, because it names one of two kinds.
           The two labels differ, which is the whole point of having either. */
        $course = $this->render('category_card');
        $coursetags = $course->query('//*[' . self::has_class('dim-card__kind') . ']');
        $this->assertSame(1, $coursetags->length);
        $this->assertSame(
            $this->example_context('category_card')['kindlabel'],
            trim($coursetags->item(0)->textContent),
            'A course card names its own kind from the data, which is what a poster never needs to.'
        );
        $this->assertNotSame(
            $expected,
            trim($coursetags->item(0)->textContent),
            'And the two kinds read differently, or the tag tells nothing apart.'
        );
    }

    /**
     * A poster's cover film is muted, looping, unfetched and drawn without browser controls, and its controls name the area.
     *
     * Nothing in the pipeline reads a template's attributes, and every one of these is a decision
     * rather than a detail: `controls` would put the browser's own bar over a poster;
     * `preload="none"` is what keeps a page of posters from fetching a film each before anybody
     * hovers; `muted` and `playsinline` are what let a browser start one at all, and `autoplay` is
     * exactly what this design does not do. The still is asserted to be written before the film,
     * because the stylesheet has to paint the film under it and a later positioned sibling paints
     * on top.
     *
     * The film's band - the element the module binds its hover to - is the poster's whole card,
     * and the poster's one link is its control, stretched over the card; the film's controls are
     * its siblings and come after it, so the area is the first Tab stop and its three controls the
     * next, each naming the area (the string helper escapes the name once, which is why the
     * example's name holds an ampersand).
     */
    public function test_the_card_plays_its_cover_video_muted_and_draws_no_browser_controls(): void {
        $this->resetAfterTest();
        $name = $this->example_context('category_directory_card')['name'];
        $this->assertStringContainsString('&', $name, 'Precondition: the example\'s name tests the escaping.');
        $card = $this->render('category_directory_card');
        $band = '//article[@data-region="dim-area"]/*[@data-region="dim-cover-media"]';
        $media = $band . '/*[' . self::has_class('dim-poster__media') . ']';

        $found = $card->query($media . '/video[@data-region="dim-cover-video"]');
        $this->assertSame(1, $found->length, 'The film is inside the poster\'s media, where the still is.');
        $video = $found->item(0);
        foreach (['muted', 'playsinline', 'loop'] as $flag) {
            $this->assertTrue($video->hasAttribute($flag), 'The video lost its ' . $flag . ' attribute.');
        }
        $this->assertSame('none', $video->getAttribute('preload'), 'Nothing of the film is fetched before a play.');
        $this->assertFalse($video->hasAttribute('controls'), 'The browser\'s own bar is never drawn over a poster.');
        $this->assertFalse($video->hasAttribute('autoplay'), 'And nothing plays until a pointer or a press says so.');

        $source = $card->query($media . '/video/source');
        $this->assertSame(1, $source->length, 'The address is on a source element, with its type beside it.');
        $this->assertNotSame('', $source->item(0)->getAttribute('src'));
        $this->assertSame(
            'video/mp4',
            $source->item(0)->getAttribute('type'),
            'The type is the one the server matched from the file\'s own bytes.'
        );

        // The order of the two layers, which is what the stylesheet's stack has to agree with.
        $layers = [];
        foreach ($card->query($media . '/*') as $child) {
            $layers[] = $child->nodeName;
        }
        $this->assertContains('img', $layers);
        $this->assertLessThan(
            array_search('video', $layers, true),
            array_search('img', $layers, true),
            'The still is written before the film: the stylesheet paints the film under it.'
        );

        // The cluster: real buttons, names from strings that name the area, aria-pressed only where it is a toggle.
        $controls = $card->query($band . '/*[@data-region="dim-cover-controls"]');
        $this->assertSame(1, $controls->length);
        /* The gate class is covervideo's own and not the showcase's dim-js-only, which follows the
           vitrine module's mark: that module is not armed for a visitor or the guest, who see
           cover films too, and dim-js written from here would also hide the toolbar's
           no-JavaScript submit - the only filter control they have. */
        $this->assertStringContainsString(
            'dim-cover-js-only',
            $controls->item(0)->getAttribute('class'),
            'Nothing here works without the module, so nothing here is shown without it.'
        );
        $this->assertStringNotContainsString(
            ' dim-js-only',
            ' ' . $controls->item(0)->getAttribute('class'),
            'The vitrine module\'s mark cannot stand for this module being present.'
        );
        foreach (
            [
                'dim-cover-play' => ['category_cover_play', 'false'],
                'dim-cover-mute' => ['category_cover_mute', 'true'],
            ] as $action => $expected
        ) {
            [$key, $pressed] = $expected;
            $buttons = $card->query('//*[@data-region="dim-cover-controls"]/button[@data-action="' . $action . '"]');
            $this->assertSame(1, $buttons->length, $action . ' is no longer a button of the cluster.');
            $this->assertSame('button', $buttons->item(0)->getAttribute('type'), 'A button inside no form is type button.');
            $this->assertSame(
                $pressed,
                $buttons->item(0)->getAttribute('aria-pressed'),
                $action . ' starts in the wrong state: every hover begins muted and stopped.'
            );
            $this->assertSame(
                get_string($key, 'theme_dimensions', $name),
                trim($buttons->item(0)->textContent),
                'An icon-only control names what it plays: the area, escaped once.'
            );
        }
        $fullscreen = $card->query('//button[@data-action="dim-cover-fullscreen"]');
        $this->assertSame(1, $fullscreen->length);
        $this->assertFalse($fullscreen->item(0)->hasAttribute('aria-pressed'), 'Full screen is an action, not a toggle.');
        $this->assertSame(
            get_string('category_cover_fullscreen', 'theme_dimensions', $name),
            trim($fullscreen->item(0)->textContent)
        );
        $progress = $card->query('//*[@data-region="dim-cover-progress"]');
        $this->assertSame(1, $progress->length);
        $this->assertSame('true', $progress->item(0)->getAttribute('aria-hidden'), 'The bar is decoration, not a control.');

        // One link per poster, its control, and nothing interactive inside it; the cluster comes after it.
        $links = $card->query('//article[@data-region="dim-area"]//a');
        $this->assertSame(1, $links->length, 'The poster is one Tab stop of its own, then its film\'s three.');
        $this->assertSame('dim-poster-control', $links->item(0)->getAttribute('data-region'));
        $this->assertSame(0, $card->query('//a//button | //a//a')->length, 'Interactive content cannot sit inside an anchor.');
        $order = [];
        foreach ($card->query('//a[@data-region="dim-poster-control"] | //button[@data-action]') as $node) {
            $order[] = $node->nodeName;
        }
        $this->assertSame(['a', 'button', 'button', 'button'], $order, 'The area first, its film\'s controls after it.');
    }

    /**
     * A poster with no cover film draws none of the cluster, and keeps its still.
     *
     * The second half of the rule above, and it needs a context of its own: a template carries
     * one example and this one is the fuller case on purpose, so that the Mustache lint renders
     * the whole markup. The still and the band's own hook are the controls - without them this
     * would pass just as well against a partial that had stopped rendering anything at all.
     */
    public function test_a_card_with_no_cover_video_draws_none_of_the_cluster(): void {
        $this->resetAfterTest();
        $context = $this->example_context('category_directory_card');
        $this->assertTrue($context['hasvideo'], 'Precondition: the example is the poster WITH a film.');
        $silent = $this->render_context('category_directory_card', ['hasvideo' => false] + $context);

        foreach (['dim-cover-video', 'dim-cover-controls', 'dim-cover-progress'] as $region) {
            $this->assertSame(
                0,
                $silent->query('//*[@data-region="' . $region . '"]')->length,
                $region . ' is drawn on a poster that has no film to play.'
            );
        }
        $this->assertSame(0, $silent->query('//video')->length);
        $this->assertSame(0, $silent->query('//button')->length, 'And no control of any kind.');
        $this->assertSame(
            1,
            $silent->query('//img[' . self::has_class('dim-poster__img') . ']')->length,
            'Control: the still is drawn exactly as before.'
        );
        $this->assertSame(
            1,
            $silent->query('//*[@data-region="dim-cover-media"]')->length,
            'And the band keeps its own hook, which is what the module adds the playing class to.'
        );
        $this->assertStringNotContainsString(
            'dim-poster--film',
            $silent->query('//article[@data-region="dim-area"]')->item(0)->getAttribute('class'),
            'Nor does it say it has a film: the stylesheet pans a photograph that has none.'
        );
    }

    /**
     * The poster's watermark, twice or not at all, and a visitor's listed area as text.
     *
     * The mark is written once under the text and once in the list view's thumbnail, decorative
     * both times; its crest arrives in the style attribute and the folder is a class the
     * stylesheet fills. The text poster draws no still, no crest and no mark whatever the
     * context would have allowed.
     */
    public function test_the_watermark_is_written_twice_and_the_text_poster_draws_none(): void {
        $this->resetAfterTest();
        $base = $this->example_context('category_directory_card');
        $mark = "https://example.com/pluginfile.php/31/theme_dimensions/categorybadge1/7/abc/crest.png";
        $banded = $this->render_context('category_directory_card', [
            'hasimage' => false,
            'imageurl' => null,
            'hasvideo' => false,
            'variant' => 'band',
            'hasmark' => true,
            'markfolder' => false,
            'style' => "--dim-card-band: #070093; --dim-card-ink: #ffffff; --dim-mark-a: 0.20; --dim-mark: url('{$mark}');",
        ] + $base);
        $marks = $banded->query('//span[' . self::has_class('dim-poster__mark') . '][@aria-hidden="true"]');
        $this->assertSame(2, $marks->length, 'Once under the text, once in the thumbnail.');
        foreach (['dim-poster__media', 'dim-poster__base'] as $parent) {
            $this->assertSame(
                1,
                $banded->query('//*[' . self::has_class($parent) . ']/span[' . self::has_class('dim-poster__mark') . ']')->length,
                'One mark in ' . $parent . '.'
            );
        }
        $this->assertSame(0, $banded->query('//*[' . self::has_class('dim-poster__mark--folder') . ']')->length);
        $item = $banded->query('//article[@data-region="dim-area"]')->item(0);
        $this->assertStringContainsString('dim-poster--band', $item->getAttribute('class'));
        $this->assertMatchesRegularExpression(
            "~^(--dim-card-(band|ink): #[0-9a-f]{6}; ?|--dim-mark-a: [01]\\.\\d{2}; ?|--dim-mark: url\\('[^'&]*'\\); ?)+$~",
            $item->getAttribute('style'),
            'The attribute is the closed vocabulary the server builds.'
        );

        $folder = $this->render_context('category_directory_card', [
            'hasimage' => false,
            'imageurl' => null,
            'hasmark' => true,
            'markfolder' => true,
        ] + $base);
        $this->assertSame(2, $folder->query('//span[' . self::has_class('dim-poster__mark--folder') . ']')->length);

        $text = $this->render_context('category_directory_card', [
            'istext' => true,
            'variant' => 'text',
            'hasimage' => false,
            'imageurl' => null,
            'hasvideo' => false,
            'badges' => [],
            'hasbadges' => false,
            'hasmark' => false,
            'markfolder' => false,
        ] + $base);
        $this->assertSame(0, $text->query('//img')->length, 'A text poster draws no picture of any kind.');
        $this->assertSame(0, $text->query('//*[' . self::has_class('dim-poster__mark') . ']')->length);
        $this->assertStringContainsString(
            'dim-poster--text',
            $text->query('//article[@data-region="dim-area"]')->item(0)->getAttribute('class')
        );
    }

    /**
     * The editor's pair preview renders the name, the caption and both colours, and is TEXT.
     *
     * It is the one element of the appearance form that carries a colour, and the mistakes
     * available to it are all invisible to the pipeline: a band with no name on it says nothing
     * about the pair; a caption marked aria-hidden (or drawn as an image) hides from a screen
     * reader the only sentence saying what the box is; and a style attribute holding anything
     * but the two colours is the CSS-context sink this feature has one sanitiser for. Rendered
     * from the template's own example context, so what is parsed is what a browser receives -
     * which also proves that context is there and usable, the thing the mustache lint needs.
     */
    public function test_the_pair_preview_renders_the_name_the_caption_and_both_colours(): void {
        $this->resetAfterTest();

        $context = $this->example_context('categoryhero_pairpreview');
        $this->assertNotEmpty($context, 'An empty example context renders nothing and proves nothing.');
        $preview = $this->render('categoryhero_pairpreview');

        $band = $preview->query('//*[@data-region="dim-pair-preview"]');
        $this->assertSame(1, $band->length, 'The preview no longer writes the hook the module paints.');
        $this->assertSame(
            $context['style'],
            $band->item(0)->getAttribute('style'),
            'The band no longer carries the pair the form handed it.'
        );
        $this->assertMatchesRegularExpression(
            '~^background-color: #[0-9a-f]{6}; color: #[0-9a-f]{6};$~',
            $band->item(0)->getAttribute('style'),
            'Something other than the two colours is in the style attribute.'
        );

        $name = $preview->query('//*[' . self::has_class('dim-pair-preview__name') . ']');
        $this->assertSame(1, $name->length, 'The category has no name on the band.');
        $this->assertSame($context['name'], trim($name->item(0)->textContent));

        $caption = $preview->query('//*[' . self::has_class('dim-pair-preview__caption') . ']');
        $this->assertSame(1, $caption->length, 'The band says nothing about what it is.');
        $this->assertSame(
            get_string('categoryhero_pairpreview', 'theme_dimensions'),
            trim($caption->item(0)->textContent),
            'The caption is not the string both language packs carry.'
        );
        $this->assertSame(
            '',
            $caption->item(0)->getAttribute('aria-hidden'),
            'The one sentence naming this box is hidden from a screen reader.'
        );
        $this->assertSame(
            0,
            $preview->query('//*[@role="img"]')->length,
            'The preview is text on a coloured box and must not claim to be a picture.'
        );
    }

    /**
     * Every cell of the scope is written, and a cell with nothing to offer carries the hidden attribute.
     *
     * The filter line's client measures the line with every cell drawn, so the step it takes follows
     * the width and the language and never the counts; it unhides an absent cell for the measure and
     * hides it again, which needs the cell in the document. Hidden, the cell and its radio are out of
     * the picture, of the accessibility tree and of the radio group's arrow keys.
     *
     * Changes that must make it fail: an absent cell left visible; an absent cell not written at all.
     */
    public function test_an_absent_scope_cell_is_written_hidden(): void {
        $this->resetAfterTest();
        $toolbar = $this->render('category_toolbar');
        $scope = '//*[@data-region="dim-line"]/fieldset[@data-role="scope"]';
        $this->assertSame(1, $toolbar->query($scope . '//input[@value="open"][@hidden]')->length, 'The radio, hidden.');
        $this->assertSame(1, $toolbar->query($scope . '//label[@for="dim-chip-7-open"][@hidden]')->length, 'Its label, hidden.');
        $this->assertSame(
            0,
            $toolbar->query(
                $scope . '//input[@value="enrolled"][@hidden] | ' . $scope . '//label[@for="dim-chip-7-enrolled"][@hidden]'
            )->length,
            'A cell with something to offer is drawn as ever.'
        );
    }
}
