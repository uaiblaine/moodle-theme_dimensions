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

use Behat\Mink\Exception\ElementNotFoundException;
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

    /**
     * Click on an entry in the user menu: in the profile layer, where the theme's module took the user button over.
     *
     * Core's step presses the user button and then clicks the link in Boost Union's dropdown, which the button no longer
     * opens once the layer is armed. So the button is pressed, which opens the layer, and the link is clicked there.
     * An entry the layer does not draw as a link of its own (Profile, which is its header's "View profile" button, or
     * an item core shows as text) is followed by its address in Boost Union's dropdown, which stays on the page. Without
     * JavaScript, or where no layer is armed, core's step runs unchanged.
     *
     * @Given /^I follow "(?P<nodetext_string>(?:[^"]|\\")*)" in the user menu$/
     *
     * @param string $nodetext The entry's text, or part of it.
     * @return void
     */
    public function i_follow_in_the_user_menu($nodetext) {
        if (!$this->running_javascript() || !$this->getSession()->getPage()->find('css', self::ARMED_LAYER)) {
            parent::i_follow_in_the_user_menu($nodetext);
            return;
        }

        $this->execute('behat_general::i_click_on', ['#user-menu-toggle', 'css_element']);
        if ($this->link_in(self::LAYER, $nodetext, true)) {
            $this->execute('behat_general::i_click_on_in_the', [$nodetext, 'link', self::LAYER, 'css_element']);
            return;
        }

        $fallback = $this->link_in('.usermenu .dropdown-menu', $nodetext, false);
        if ($fallback === null || (string) $fallback->getAttribute('href') === '') {
            throw new ElementNotFoundException($this->getSession(), 'link', 'text', $nodetext);
        }
        $this->execute('behat_general::i_visit', [(string) $fallback->getAttribute('href')]);
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
