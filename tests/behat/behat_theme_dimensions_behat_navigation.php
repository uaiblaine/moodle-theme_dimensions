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
 * Behat navigation-related step definition overrides for the Dimensions theme.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2022 Luca Bösch, BFH Bern University of Applied Sciences luca.boesch@bfh.ch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.
// For that reason, we can't even rely on $CFG->admin being available here.

require_once(__DIR__ . '/../../../boost_union/tests/behat/behat_theme_boost_union_behat_navigation.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Navigation-related step definition overrides for the Dimensions theme.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2022 Luca Bösch, BFH Bern University of Applied Sciences luca.boesch@bfh.ch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_theme_dimensions_behat_navigation extends behat_theme_boost_union_behat_navigation {
    /** @var string The profile layer, once amd/src/profilelayer.js has armed it and taken the user button over. */
    private const ARMED_LAYER = "[data-region='dimensions-profile'].dimensions-layer-js";

    /** @var string The profile layer, to look for a link in. */
    private const LAYER = "[data-region='dimensions-profile']";

    /** @var string The category navigation's row of nav pills, on the hero or after a destination page's header. */
    private const NAVPILLS = "[data-region='dim-navpills']";

    /**
     * Go to a destination of the current page's administration: on the category navigation's row, where it is drawn.
     *
     * The theme draws the category's secondary navigation as a row of nav pills in place of core's tabs (D97), so
     * core's step, which looks for the tabs' more menu, finds nothing on a category page. On a page with the row,
     * the first node of the path is followed on the row, inside the group or "More" that holds it (D102), and the
     * rest of the path, if any, on the page it leads to, as core's step does after a tab. A page without the row, or
     * whose row does not hold the node, runs core's step unchanged.
     *
     * This overrides the METHOD of core's step `I navigate to "..." in current page administration`
     * (lib/tests/behat/behat_navigation.php); the annotation is inherited, as for the user menu's step above.
     *
     * @param string $nodetext The node to follow, which may be a path ("Permissions > Assign roles").
     * @return void
     */
    public function i_navigate_to_in_current_page_administration($nodetext) {
        $nodelist = array_map('trim', explode('>', $nodetext));
        $link = $this->row_link($nodelist[0]);
        if ($link === null) {
            parent::i_navigate_to_in_current_page_administration($nodetext);
            return;
        }
        $this->follow_on_the_row($link);
        if (count($nodelist) > 1) {
            $this->select_on_administration_page(array_slice($nodelist, 1));
        }
    }

    /**
     * Follow a destination of the secondary navigation: on the category navigation's row, where it is drawn.
     *
     * The same answer as {@see self::i_navigate_to_in_current_page_administration()} for core's step
     * `I select "..." from secondary navigation`, whose annotation is inherited: the link is followed on the row,
     * inside its group or "More", and a page without the row, or whose row does not hold it, runs core's step.
     *
     * @param string $link The destination's text.
     * @return void
     */
    public function i_select_from_secondary_navigation(string $link) {
        $node = $this->row_link($link);
        if ($node === null) {
            parent::i_select_from_secondary_navigation($link);
            return;
        }
        $this->follow_on_the_row($node);
    }

    /**
     * The destination of the row whose text is the given one, or else holds it; null without a row or such a link.
     *
     * Every destination is a link inside the row's nav, a plain pill, an item of a group's menu or of "More", whether
     * or not a script has moved it, so the row's markup is read as it is, without waiting. A group's pill is a summary,
     * never a link, so a group's name is no destination.
     *
     * @param string $text The destination's text.
     * @return \Behat\Mink\Element\NodeElement|null
     */
    private function row_link(string $text): ?\Behat\Mink\Element\NodeElement {
        $row = $this->getSession()->getPage()->find('css', self::NAVPILLS);
        if ($row === null) {
            return null;
        }
        $text = trim($text);
        $partial = null;
        foreach ($row->findAll('css', 'a[href]') as $link) {
            $label = trim((string) preg_replace('~\s+~u', ' ', strip_tags($link->getHtml())));
            if ($label === $text) {
                return $link;
            }
            if ($partial === null && str_contains($label, $text)) {
                $partial = $link;
            }
        }
        return $partial;
    }

    /**
     * Follow a link of the row, opening first the group or "More" that holds it when a script runs and it is closed.
     *
     * Without JavaScript the link is in the page and followed as it is: a closed disclosure hides nothing from the
     * browser kit. A summary is not a button to press: it is clicked as an element.
     *
     * @param \Behat\Mink\Element\NodeElement $link The link.
     * @return void
     */
    private function follow_on_the_row(\Behat\Mink\Element\NodeElement $link): void {
        if ($this->running_javascript() && !$link->isVisible()) {
            $disclosure = $link->find('xpath', 'ancestor::details[1]');
            if ($disclosure !== null && !$disclosure->hasAttribute('open')) {
                $summary = $disclosure->find('xpath', './summary');
                if ($summary !== null) {
                    $this->execute('behat_general::i_click_on', [$summary, 'NodeElement']);
                }
            }
        }
        $this->execute('behat_general::i_click_on', [$link, 'NodeElement']);
    }

    /**
     * Click on an entry in the user menu: in the profile layer, where the theme's module took the user button over.
     *
     * Core's step presses the user button and then clicks the link in Boost Union's dropdown, which the button no longer
     * opens once the layer is armed. So the button is pressed (unless the layer is already open), which opens the
     * layer, and the link is clicked there. The one item the layer does not draw as a row is core's Profile, which is
     * the header's "View profile" button: that button is clicked. Any other entry the layer does not carry fails the
     * step, so a layer that dropped an item shows. Without JavaScript, or where no layer is armed, core's step runs
     * unchanged.
     *
     * This overrides the METHOD of core's step `I follow "..." in the user menu` (lib/tests/behat/behat_navigation.php);
     * the step annotation is inherited on purpose and must not be repeated here, or Behat sees the definition twice
     * and every step fails.
     *
     * @param string $nodetext The entry's text, or part of it.
     * @return void
     */
    public function i_follow_in_the_user_menu($nodetext) {
        if (!$this->running_javascript()) {
            parent::i_follow_in_the_user_menu($nodetext);
            return;
        }
        $layer = $this->getSession()->getPage()->find('css', self::ARMED_LAYER);
        if ($layer === null) {
            parent::i_follow_in_the_user_menu($nodetext);
            return;
        }

        // A layer that is already open stays open: pressing the button again would close it.
        if ($layer->hasAttribute('hidden')) {
            $this->execute('behat_general::i_click_on', ['#user-menu-toggle', 'css_element']);
        }
        if ($this->link_in(self::LAYER, $nodetext, true)) {
            $this->execute('behat_general::i_click_on_in_the', [$nodetext, 'link', self::LAYER, 'css_element']);
            return;
        }

        // Core's Profile item is lifted out of the list into the header's button, which carries the same address.
        if (trim($nodetext) === get_string('profile')) {
            $this->execute('behat_general::i_click_on_in_the', [get_string('viewprofile'), 'link', self::LAYER, 'css_element']);
            return;
        }

        throw new ExpectationException(
            'The profile layer is armed and carries no link "' . $nodetext . '": the entry is missing from the layer.',
            $this->getSession()
        );
    }

    /**
     * The first link inside a container whose text holds the given text, as core's partial link selector matches it.
     *
     * Read from the markup, without waiting: the container is on the page, and the link is or is not in it.
     *
     * @param string $container The container's CSS selector.
     * @param string $text The link's text, or part of it.
     * @param bool $visible Whether only a link the person can see counts.
     * @return \Behat\Mink\Element\NodeElement|null
     */
    private function link_in(string $container, string $text, bool $visible): ?\Behat\Mink\Element\NodeElement {
        $node = $this->getSession()->getPage()->find('css', $container);
        if ($node === null) {
            return null;
        }
        foreach ($node->findAll('css', 'a[href]') as $link) {
            $label = trim((string) preg_replace('~\s+~u', ' ', strip_tags($link->getHtml())));
            if (str_contains($label, $text) && (!$visible || $link->isVisible())) {
                return $link;
            }
        }

        return null;
    }
}
