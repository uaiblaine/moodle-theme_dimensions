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
 * Theme Dimensions - Tests for the focus mode stylesheet and its class vocabulary
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Guards the two properties of the focus mode stylesheet that nothing else in the pipeline reads.
 *
 * Stylelint reads CSS but knows nothing about which page a rule reaches, and neither phpcs nor
 * the Mustache lint reads a class name at all. So a rule escaping the body class and reaching
 * every page of the site, and a Bootstrap 4 spelling written into a template or module, are
 * checked nowhere else.
 *
 * The stylesheet is concatenated into the theme's main SCSS, so an unscoped "display: none" on
 * .navbar.fixed-top would hide the navbar on every page of the site.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class focusmode_scss_test extends \basic_testcase {
    /**
     * @var array The class prefixes a top-level selector of the stylesheet may be scoped to.
     *
     * The two body classes - dimensions-focusable on every page that can switch in place, in either
     * skin, and dimensions-focusmode (with its -expanded companion) on the focused skin - and the
     * feature's own class prefixes, which stay accepted scopes because nothing core or Boost Union
     * ships carries them, so a selector wearing one cannot reach markup this theme did not draw.
     */
    private const SCOPES = ['.dimensions-focusable', '.dimensions-focusmode', '.dimensions-focusbar', '.dimensions-focus-'];

    /**
     * Absolute path to the theme root.
     *
     * @return string The plugin directory, without a trailing separator.
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 2);
    }

    /**
     * The focus mode stylesheet, with its comments removed.
     *
     * @return string The stylesheet source.
     */
    private function stylesheet(): string {
        return $this->strip_comments(file_get_contents($this->plugin_root() . '/scss/focusmode.scss'));
    }

    /**
     * Remove block and line comments from a stylesheet.
     *
     * The line-comment pattern requires whitespace or a line start before the slashes, so a
     * protocol inside a value is not mistaken for the start of a comment.
     *
     * @param string $source The stylesheet source.
     * @return string The source without its comments.
     */
    private function strip_comments(string $source): string {
        $source = preg_replace('~/\*.*?\*/~s', '', $source);
        return preg_replace('~(^|\s)//[^\n]*~m', '$1', $source);
    }

    /**
     * Every selector of the stylesheet which is not already inside another rule.
     *
     * Walks the braces rather than matching them, because a regular expression cannot tell a
     * nested rule from a top-level one. A selector counts as top-level when it sits at the root
     * of the file, or directly inside an at-rule at the root - the reduced-motion block is the
     * one this file really has, and its contents need the same scope as everything else.
     *
     * @param string $scss The stylesheet source, comments already removed.
     * @return array The selector strings, whitespace collapsed.
     */
    private function top_level_selectors(string $scss): array {
        $selectors = [];
        $stack = [];
        $buffer = '';

        $length = strlen($scss);
        for ($i = 0; $i < $length; $i++) {
            $char = $scss[$i];
            if ($char === '{') {
                $selector = trim((string) preg_replace('~\s+~', ' ', $buffer));
                $depth = count($stack);
                $isatrule = str_starts_with($selector, '@');
                $underatrule = $depth === 1 && str_starts_with($stack[0], '@');
                if (!$isatrule && ($depth === 0 || $underatrule)) {
                    $selectors[] = $selector;
                }
                $stack[] = $selector;
                $buffer = '';
            } else if ($char === '}') {
                array_pop($stack);
                $buffer = '';
            } else if ($char === ';') {
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }

        return $selectors;
    }

    /**
     * The theme's ordinary stylesheet, with its comments removed.
     *
     * The pill both ways through focus mode wear is defined here and not in focusmode.scss,
     * because the entry button renders on a page which carries no focus body class.
     *
     * @return string The stylesheet source.
     */
    private function post_stylesheet(): string {
        return $this->strip_comments(file_get_contents($this->plugin_root() . '/scss/post.scss'));
    }

    /**
     * The declarations of one rule of a stylesheet, by its exact selector.
     *
     * Only the rule's own declarations: a nested block contributes nothing, so a property named
     * inside a media query or a pseudo-class cannot be mistaken for one on the rule itself.
     *
     * @param string $scss The stylesheet source, comments already removed.
     * @param string $selector The selector to find, spelled exactly as the file spells it.
     * @return string The declaration block, or the empty string when the selector is absent.
     */
    private function declarations_of(string $scss, string $selector): string {
        $at = strpos($scss, $selector . ' {');
        if ($at === false) {
            return '';
        }

        $body = '';
        $depth = 0;
        for ($i = $at + strlen($selector) + 1; $i < strlen($scss); $i++) {
            $char = $scss[$i];
            if ($char === '{') {
                $depth++;
                if ($depth === 1) {
                    continue;
                }
            } else if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }
            if ($depth === 1) {
                $body .= $char;
            }
        }

        return $body;
    }

    /**
     * Every file of this theme whose contents can put a class name in front of a person.
     *
     * amd/build is skipped: it is generated from amd/src, and a finding there is a finding in
     * the source. So are the docs, which .gitattributes keeps out of the release zip anyway.
     *
     * @return array The absolute file paths, sorted.
     */
    private function markup_files(): array {
        $root = $this->plugin_root();
        $files = [];
        foreach ([$root . '/templates', $root . '/amd/src', $root . '/classes'] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if (!$file->isFile() || !in_array($file->getExtension(), ['mustache', 'js', 'php'], true)) {
                    continue;
                }
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        return $files;
    }

    /**
     * The Bootstrap 4 spellings which must never be written, and what to write instead.
     *
     * Both directions of the asymmetry are covered. The start and end spacers, the logical text
     * and float utilities and visually-hidden all exist on Moodle 5.x only through
     * bs4-compat.scss, which wraps each one in a deprecation - a red outline under
     * themedesignermode - and which Moodle 6.0 removes entirely (MDL-84465). The Bootstrap 5
     * spelling beside each of them is the one that is correct on every branch, so writing the
     * old name buys nothing at all and costs the rule an expiry date.
     *
     * data-toggle is listed with the equals sign on purpose: Boost Union's own drawer markup,
     * which the focus mode once copied verbatim, carries data-toggler and data-target, and
     * neither is a Bootstrap attribute.
     *
     * @return array Regular expression => the spelling to use instead.
     */
    private function bootstrap4_spellings(): array {
        return [
            '~(?<![\w-])(m[lr]|p[lr])-(0|1|2|3|4|5|auto)(?![\w-])~' => 'the ms-/me-/ps-/pe- spacers',
            '~(?<![\w-])text-(left|right)(?![\w-])~' => 'text-start / text-end',
            '~(?<![\w-])float-(left|right)(?![\w-])~' => 'float-start / float-end',
            '~(?<![\w-])border-(left|right)(?![\w-])~' => 'border-start / border-end',
            '~(?<![\w-])rounded-(left|right)(?![\w-])~' => 'rounded-start / rounded-end',
            '~(?<![\w-])sr-only(?![\w-])~' => 'visually-hidden',
            '~(?<![\w-])no-gutters(?![\w-])~' => 'g-0',
            '~data-toggle=~' => 'data-bs-toggle',
        ];
    }

    /**
     * Whether a source line is prose rather than markup.
     *
     * These rules are about what reaches a browser. A comment naming an attribute in order to
     * explain the rule - as this file's own neighbours do - is not a breach of it.
     *
     * @param string $line One raw source line.
     * @return bool True when the line opens with a PHP, JS or Mustache comment marker.
     */
    private function is_comment_line(string $line): bool {
        $trimmed = ltrim($line);

        return $trimmed === ''
            || str_starts_with($trimmed, '//')
            || str_starts_with($trimmed, '/*')
            || str_starts_with($trimmed, '*')
            || str_starts_with($trimmed, '{{!');
    }

    /**
     * Every rule of the stylesheet is scoped to something only focus mode puts on a page.
     *
     * @return void
     */
    public function test_every_top_level_selector_is_scoped_to_focus_mode(): void {
        $selectors = $this->top_level_selectors($this->stylesheet());

        $this->assertNotEmpty($selectors, 'The stylesheet has no rules at all, so this proves nothing.');

        foreach ($selectors as $selector) {
            foreach (explode(',', $selector) as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                $scoped = false;
                foreach (self::SCOPES as $scope) {
                    if (str_contains($part, $scope)) {
                        $scoped = true;
                        break;
                    }
                }
                $this->assertTrue($scoped, 'Unscoped selector, it would reach every page: ' . $part);
            }
        }
    }

    /**
     * The scope check really can tell an unscoped selector from a scoped one.
     *
     * Without this, a walker which returned nothing - a stray brace, a comment stripper eating
     * the file - would make the test above pass over an unscoped stylesheet. The fixture's
     * unscoped rule sits inside an at-rule, the one level of nesting the walker must look into.
     *
     * @return void
     */
    public function test_the_scope_check_sees_an_unscoped_selector(): void {
        $fixture = "body.dimensions-focusmode .x { color: red; }\n" .
            "@media (prefers-reduced-motion: reduce) {\n .navbar.fixed-top { transition: none; }\n}\n";

        $selectors = $this->top_level_selectors($fixture);

        $this->assertSame(['body.dimensions-focusmode .x', '.navbar.fixed-top'], $selectors);
    }

    /**
     * Every custom property the stylesheet reads is declared somewhere it can reach.
     *
     * An unresolved var() is not a fallback to the literal: the whole declaration is invalid at
     * computed-value time, so "background: var(--missing)" is not the default background, it is
     * no background at all. That failure is invisible in the source and looks like a missing
     * rule in the browser. Bootstrap's own --bs-* names are exempt because core declares them.
     *
     * @return void
     */
    public function test_every_custom_property_it_reads_is_declared(): void {
        $stylesheet = $this->stylesheet();
        $tokens = $this->strip_comments(file_get_contents($this->plugin_root() . '/scss/post.scss'));

        preg_match_all('~(--[a-z0-9-]+)\s*:~i', $tokens . $stylesheet, $declared);
        preg_match_all('~var\(\s*(--[a-z0-9-]+)~i', $stylesheet, $read);

        $this->assertNotEmpty($read[1], 'The stylesheet reads no token at all, so this proves nothing.');

        foreach (array_unique($read[1]) as $property) {
            if (str_starts_with($property, '--bs-')) {
                continue;
            }
            $this->assertContains(
                $property,
                $declared[1],
                $property . ' is read by scss/focusmode.scss and declared nowhere it can see.'
            );
        }
    }

    /**
     * The stylesheet wins on specificity and never on force.
     *
     * Moodle's stylelint config forbids the keyword, but stylelint runs only with grunt; this
     * assertion holds in a PHPUnit run as well.
     *
     * @return void
     */
    public function test_the_stylesheet_uses_no_important(): void {
        $this->assertStringNotContainsString('!important', $this->stylesheet());
    }

    /**
     * No Bootstrap 4 spelling reaches a template, a module or a renderer.
     *
     * This theme supports Moodle 5.2 alone, so the old names still resolve - through
     * bs4-compat.scss, with a deprecation attached and a removal already scheduled. The rule is
     * therefore not about breakage today but about writing a vocabulary with a known expiry
     * date, and it is enforced here because nothing else in the pipeline reads a class name.
     *
     * @return void
     */
    public function test_no_bootstrap_4_only_class_name_reaches_the_browser(): void {
        $files = $this->markup_files();
        $this->assertNotEmpty($files, 'No file was scanned, so this proves nothing.');

        $root = $this->plugin_root();
        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            foreach ($lines as $number => $line) {
                if ($this->is_comment_line($line)) {
                    continue;
                }
                foreach ($this->bootstrap4_spellings() as $pattern => $replacement) {
                    $where = str_replace($root . '/', '', $file) . ':' . ($number + 1);
                    $this->assertDoesNotMatchRegularExpression(
                        $pattern,
                        $line,
                        $where . ' carries a Bootstrap 4 spelling; write ' . $replacement . ' instead.'
                    );
                }
            }
        }
    }

    /**
     * The vocabulary sweep really matches the spellings it lists.
     *
     * Each pattern is exercised against a line that must match and against the Bootstrap 5
     * spelling of the same utility, which must not, so a pattern that matches nothing cannot
     * leave the sweep above passing.
     *
     * @return void
     */
    public function test_the_vocabulary_sweep_matches_what_it_claims(): void {
        $offenders = [
            '<div class="ml-3 text-left float-right sr-only no-gutters border-left rounded-right">',
            '<button data-toggle="dropdown">',
        ];
        $clean = '<div class="ms-3 text-start float-end visually-hidden g-0 border-start rounded-end" ' .
            'data-bs-toggle="dropdown" data-toggler="drawers" data-target="theme_boost-drawers-blocks">';

        foreach ($this->bootstrap4_spellings() as $pattern => $replacement) {
            $matched = false;
            foreach ($offenders as $offender) {
                $matched = $matched || (bool) preg_match($pattern, $offender);
            }
            $this->assertTrue($matched, 'The pattern for ' . $replacement . ' matches no known offender.');
            $this->assertDoesNotMatchRegularExpression($pattern, $clean);
        }
    }

    /**
     * The bar is one row which cannot become two.
     *
     * Bootstrap's .navbar declares flex-wrap: wrap, and the bar keeps that class because Boost's
     * height and border and this theme's signature band all hang off .navbar.fixed-top. Flex
     * assigns items to lines before any shrink factor is consulted, so without nowrap a narrow
     * screen puts the controls on a second row that hangs out of the bar's pinned height.
     *
     * @return void
     */
    public function test_the_bar_cannot_wrap(): void {
        $rule = $this->declarations_of($this->stylesheet(), 'body.dimensions-focusmode .dimensions-focusbar');

        $this->assertNotSame('', $rule, 'The bar rule is gone from the stylesheet.');
        $this->assertMatchesRegularExpression(
            '~flex-wrap:\s*nowrap~',
            $rule,
            'The bar must declare flex-wrap: nowrap; it is a .navbar and Bootstrap wraps those.'
        );
    }

    /**
     * The controls cluster keeps the content floor which stops it shrinking.
     *
     * A flex item's automatic minimum size is its content's, and with the cluster's children at
     * flex: 0 0 auto that floor is exactly the width of the controls - computed by the browser,
     * so it cannot fall out of step with them. Writing min-width: 0 on the cluster removes it,
     * and a long activity name then squeezes the controls below their own width. The child
     * which must still give way carries its own min-width: 0, and an item with that contributes
     * nothing to the floor.
     *
     * @return void
     */
    public function test_the_controls_cluster_keeps_its_content_floor(): void {
        $scss = $this->stylesheet();

        $cluster = $this->declarations_of($scss, 'body.dimensions-focusmode .dimensions-focusbar-actions');
        $this->assertNotSame('', $cluster, 'The controls cluster rule is gone from the stylesheet.');
        $this->assertDoesNotMatchRegularExpression(
            '~min-width:\s*0~',
            $cluster,
            'min-width: 0 on the cluster removes the floor which keeps the controls at full size.'
        );

        foreach (
            [
                'body.dimensions-focusmode .dimensions-focusbar-actions > .dimensions-focusbar-headeractions',
                'body.dimensions-focusmode .dimensions-focusbar-headeractions > .dimensions-focusbar-action',
            ] as $selector
        ) {
            $this->assertMatchesRegularExpression(
                '~min-width:\s*0~',
                $this->declarations_of($scss, $selector),
                'A header action is markup this theme did not write and has to be the one thing which gives way: ' . $selector
            );
        }
    }

    /**
     * Core's icon margin is cancelled on the three controls the bar owns, and on nothing else.
     *
     * Core gives every .icon half a rem of right margin, a pre-flexbox spacer for the label which
     * used to follow it. These controls are flex boxes whose gap is that same half rem, so on an
     * icon-only one it pushed the glyph off the button's optical centre. The container must not be
     * named: it also holds a header action, which is core's markup or a module's, and their
     * spacing is theirs to choose.
     *
     * @return void
     */
    public function test_the_icon_margin_is_cancelled_on_the_bars_own_controls_only(): void {
        $scss = $this->stylesheet();
        $selector = 'body.dimensions-focusmode .dimensions-focusbar-close .icon,' . "\n" .
            'body.dimensions-focusmode .dimensions-focusbar-morebutton .icon,' . "\n" .
            'body.dimensions-focusmode .dimensions-focusbar-width .icon';

        $rule = $this->declarations_of($scss, $selector);
        $this->assertNotSame('', $rule, 'The three controls of the bar must cancel core\'s .icon margin together.');
        $this->assertMatchesRegularExpression('~margin:\s*0~', $rule);

        $toowides = [
            'body.dimensions-focusmode .dimensions-focusbar .icon',
            'body.dimensions-focusmode .dimensions-focusbar-actions .icon',
            'body.dimensions-focusmode .dimensions-focusbar-action .icon',
        ];
        foreach ($toowides as $toowide) {
            $this->assertStringNotContainsString(
                $toowide . ' {',
                $scss,
                $toowide . ' would strip the margin from markup this theme did not write.'
            );
        }
    }

    /**
     * The overflow menu is anchored to the bar and bounded by it.
     *
     * Bootstrap disables Popper for every dropdown inside a navbar, so nothing moves this menu
     * back into the viewport and the plain CSS behind dropdown-menu-end is the whole of its
     * position. Taking the dropdown out of the positioned chain hands the containing block to the
     * fixed bar; the inset and the cap are then measured against the bar rather than against the
     * button, and the items may wrap so a long one cannot push out of the cap sideways.
     *
     * @return void
     */
    public function test_the_overflow_menu_is_anchored_to_the_bar(): void {
        $scss = $this->stylesheet();

        $this->assertMatchesRegularExpression(
            '~position:\s*static~',
            $this->declarations_of($scss, 'body.dimensions-focusmode .dimensions-focusbar-more'),
            'Without position: static the menu is placed against its own button, which on a phone is not at the edge.'
        );

        $menu = $this->declarations_of($scss, 'body.dimensions-focusmode .dimensions-focusbar-more .dropdown-menu');
        foreach (['inset-inline-end', 'max-width', 'max-height', 'overflow-y'] as $property) {
            $this->assertMatchesRegularExpression(
                '~' . preg_quote($property, '~') . ':~',
                $menu,
                'The menu must bound itself against the bar with ' . $property . '.'
            );
        }

        $this->assertMatchesRegularExpression(
            '~white-space:\s*normal~',
            $this->declarations_of($scss, 'body.dimensions-focusmode .dimensions-focusbar-actions .dropdown-item'),
            'Bootstrap sets dropdown items to nowrap, and a long one would leave the cap sideways.'
        );
    }

    /**
     * The way in and the way out of focus mode wear one pill, from one definition.
     *
     * They are the same control seconds apart, so both take the dimensions-navbar-pill mixin of
     * post.scss; this test stops either end being restyled by hand instead. Both rules are in
     * focusmode.scss, so neither reaches a page while the master switch is off.
     *
     * @return void
     */
    public function test_both_ways_through_focus_mode_wear_the_same_pill(): void {
        $post = $this->post_stylesheet();

        $this->assertStringContainsString(
            '@mixin dimensions-navbar-pill {',
            $post,
            'The shared pill definition has gone from post.scss.'
        );
        $this->assertMatchesRegularExpression(
            '~@include\s+dimensions-navbar-pill;~',
            $this->declarations_of($this->stylesheet(), 'body.dimensions-focusable .dimensions-focus-entry-button'),
            'The entry button must take the shared pill rather than a copy of it.'
        );
        $this->assertStringNotContainsString(
            'dimensions-focus-entry-button',
            $post,
            'The entry button\'s rule belongs to focusmode.scss, which is not compiled while the switch is off.'
        );
        $this->assertMatchesRegularExpression(
            '~@include\s+dimensions-navbar-pill;~',
            $this->declarations_of(
                $this->stylesheet(),
                'body.dimensions-focusmode .dimensions-focusbar .dimensions-focusbar-actions .dimensions-focusbar-close'
            ),
            'The exit control must take the shared pill rather than a copy of it.'
        );
    }

    /**
     * The spacer element is gone from both the template and the stylesheet.
     *
     * An auto margin on the controls does the same job with one box and one gap fewer, and mirrors
     * in RTL. Half a removal - a rule with no element, or an element with no rule - is the state
     * this asserts against.
     *
     * @return void
     */
    public function test_the_spacer_element_is_gone(): void {
        foreach (['/scss/focusmode.scss', '/templates/focusmode_bar.mustache'] as $file) {
            $this->assertStringNotContainsString(
                'dimensions-focusbar-spacer',
                file_get_contents($this->plugin_root() . $file),
                $file . ' still carries the spacer the auto margin replaced.'
            );
        }

        $this->assertMatchesRegularExpression(
            '~margin-inline-start:\s*auto~',
            $this->declarations_of($this->stylesheet(), 'body.dimensions-focusmode .dimensions-focusbar-actions'),
            'The auto margin is what holds the controls at the end of the row now.'
        );
    }

    /**
     * Outside the focused skin, what focus mode adds is not displayed.
     *
     * The bar and the foot of the content are in the page whichever skin it opens in, and the
     * module only moves the body class; display: none also keeps them out of the tab order and the
     * accessibility tree while the ordinary skin is shown.
     *
     * @return void
     */
    public function test_the_focus_only_parts_are_hidden_outside_the_focused_skin(): void {
        $rule = $this->declarations_of(
            $this->stylesheet(),
            'body.dimensions-focusable:not(.dimensions-focusmode) .dimensions-focus-only'
        );
        $this->assertMatchesRegularExpression('~display:\s*none~', $rule);

        foreach (['/templates/focusmode_bar.mustache', '/templates/focusmode_foot.mustache'] as $file) {
            $this->assertStringContainsString(
                'dimensions-focus-only',
                file_get_contents($this->plugin_root() . $file),
                $file . ' must mark its root focus-only.'
            );
        }
    }

    /**
     * The focused skin hides Boost Union's chrome, and keeps what the reader works with.
     *
     * The list is the coupling with Boost Union's drawers template. Named here are the pieces the
     * reduced shell used to leave out; refused are the ones it kept on purpose - the two drawers and
     * their togglers (a book's contents and a quiz's navigation are blocks), the activity header with
     * its anchor, the info banners and the footer, which in either skin is Boost Union's own.
     *
     * @return void
     */
    public function test_the_focused_skin_hides_the_chrome_and_keeps_the_drawers(): void {
        $scss = $this->stylesheet();
        $found = preg_match(
            '~((?:body\.dimensions-focusmode [^,{]+,\s*)+body\.dimensions-focusmode [^,{]+)\s*\{\s*display:\s*none;\s*\}~',
            $scss,
            $matches
        );
        $this->assertSame(1, $found, 'The chrome list is gone from the stylesheet.');
        $selectors = array_map('trim', explode(',', $matches[1]));

        foreach (
            [
                '#page-wrapper > .navbar.fixed-top:not(.dimensions-focusbar)',
                '#page-header',
                '.secondary-navigation',
                '#region-main-settings-menu',
                '#theme-block-region-outside-left',
                '#footnote',
            ] as $hidden
        ) {
            $this->assertContains('body.dimensions-focusmode ' . $hidden, $selectors, $hidden . ' must be hidden.');
        }
        $kept = ['drawers-courseindex', 'drawers-blocks', 'drawer-toggle', 'activity-header', 'infobanner', 'page-footer'];
        foreach ($kept as $name) {
            foreach ($selectors as $selector) {
                $this->assertStringNotContainsString($name, $selector, $name . ' is the reader\'s and must stay.');
            }
        }
    }

    /**
     * Core's activity information box is reduced to the description in the focused skin, never removed.
     *
     * Decision D3: the conditions are the discreet line at the foot of the content, so the box's
     * half that states them goes, while the box itself is the only place the intro is printed.
     *
     * @return void
     */
    public function test_the_activity_box_keeps_only_the_description_when_focused(): void {
        $scss = $this->stylesheet();

        $this->assertMatchesRegularExpression(
            '~display:\s*none~',
            $this->declarations_of($scss, 'body.dimensions-focusmode #region-main .activity-details .completion-info')
        );
        $this->assertStringNotContainsString('.activity-information {', $scss, 'The box is reduced, never hidden whole.');
        $this->assertStringNotContainsString('#intro {', $scss, 'The description is never hidden.');
    }

    /**
     * An empty header actions slot takes no box in the bar.
     *
     * The module moves the actions out of the slot in the ordinary skin, and a slot holding nothing
     * would still take a gap of the row; the template writes it on one line so :empty holds.
     *
     * @return void
     */
    public function test_an_empty_header_actions_slot_takes_no_box(): void {
        $this->assertMatchesRegularExpression(
            '~display:\s*none~',
            $this->declarations_of(
                $this->stylesheet(),
                'body.dimensions-focusmode .dimensions-focusbar-actions > .dimensions-focusbar-headeractions:empty'
            )
        );
        $this->assertMatchesRegularExpression(
            '~data-region="dimensions-focus-headeractions">\{\{#headeractions\}\}.*\{\{/headeractions\}\}</div>~',
            file_get_contents($this->plugin_root() . '/templates/focusmode_bar.mustache'),
            'The slot must be written on one line, or a text node defeats :empty.'
        );
    }
}
