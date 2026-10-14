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
 * Theme Dimensions - My profile renderer
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output\core_user;

use core_user\output\myprofile\category;
use core_user\output\myprofile\node;
use core_user\output\myprofile\tree;
use renderable;

/**
 * Renders the user profile in the theme's design system, from templates rather than from PHP.
 *
 * WHY A THEME. Overriding a core renderer is a theme privilege and nothing else's:
 * theme_config::renderer_prefixes() returns only theme_<name> prefixes for the active theme and
 * its parents, and Moodle has no db/renderers.php at all - that is a Totara mechanism. The class
 * name this file must carry is derived in renderer_factory_base::standard_renderer_classnames():
 * for the request $PAGE->get_renderer('core_user', 'myprofile') the prefixed candidate is
 * '\output\' . $component . '\' . $subtype . '_renderer'.
 *
 * ONE OVERRIDE, BOTH PAGES. user/profile.php and user/view.php build the same tree through
 * manager::build_tree() and hand it to the same renderer, so the site profile and the in-course
 * profile change together. What they do NOT hand over is the heading, the user picture and the
 * "About me" description: those are printed by the two scripts themselves, before the tree, and
 * only $CFG->hiddenuserfields reaches the description. Styling for them is in scss/post.scss;
 * their visibility is a matter of configuration, not of this class.
 *
 * WHAT CHANGES, beyond paint. Core renders every node of a category into one flat list, so a
 * fact ("Email address: ...") and an action ("Edit profile") sit at the same level with the same
 * weight. Here the two are separated: nodes carrying content become a definition list of facts,
 * nodes that are only a link become a list of actions at the foot of the card. Nothing is
 * dropped, reordered across categories, or invented - the tree decides what exists and in which
 * category, exactly as before.
 *
 * WHAT IS NOT FILTERED HERE, on purpose. The tree offers no removal API - node's properties are
 * all private behind a __get, and tree::add_node() throws on a duplicate name, so a callback can
 * add but never prune - which makes a renderer the only place a node could be withheld. It is
 * deliberately not used for that: on this platform the profile's privacy is configured through
 * $CFG->hiddenuserfields, which core consults while BUILDING the tree, and a second, invisible
 * rule in the presentation layer would be a place for the two to disagree.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class myprofile_renderer extends \core_user\output\myprofile\renderer {
    /**
     * @var string[] Categories drawn before every other, in this order: what a profile owner
     *     comes to the page to change. auth_loginsteps' Security area, which that plugin decides
     *     and this theme only places (a category with no "after" is appended where it was added).
     */
    private const FIRST_CATEGORIES = ['loginsteps_security'];

    /**
     * Render the whole profile.
     *
     * @param tree $tree The profile tree.
     * @return string HTML.
     */
    public function render_tree(tree $tree) {
        $categories = [];
        $first = [];
        foreach ($tree->categories as $category) {
            $context = $this->category_context($category);
            if ($context === null) {
                continue;
            }
            if (in_array((string) $category->name, self::FIRST_CATEGORIES, true)) {
                $first[] = $context;
            } else {
                $categories[] = $context;
            }
        }
        $categories = array_merge($first, $categories);

        if (empty($categories)) {
            return '';
        }

        return $this->render_from_template('theme_dimensions/core_user/myprofile_tree', [
            'categories' => $categories,
        ]);
    }

    /**
     * Render a single category.
     *
     * Kept working on its own because it is a public method of the class being replaced, and
     * anything already calling $OUTPUT->render($category) must keep getting a card back.
     *
     * @param category $category The category.
     * @return string HTML, empty when the category holds no node.
     */
    public function render_category(category $category) {
        $context = $this->category_context($category);
        if ($context === null) {
            return '';
        }

        return $this->render_from_template('theme_dimensions/core_user/myprofile_category', $context);
    }

    /**
     * Render a single node.
     *
     * @param node $node The node.
     * @return string HTML.
     */
    public function render_node(node $node) {
        $context = $this->node_context($node);
        $context['isfact'] = ($context['content'] !== '');

        return $this->render_from_template('theme_dimensions/core_user/myprofile_node', $context);
    }

    /**
     * Build the template context for one category, splitting its nodes into facts and actions.
     *
     * @param category $category The category.
     * @return array|null The context, or null when the category holds no node and must not print.
     */
    protected function category_context(category $category): ?array {
        $facts = [];
        $actions = [];

        foreach ($category->nodes as $node) {
            $context = $this->node_context($node);
            if ($context['content'] !== '') {
                $facts[] = $context;
            } else if ($context['hasurl'] || $context['title'] !== '') {
                $actions[] = $context;
            }
        }

        // Core prints nothing for a category with no nodes, and neither does this. A card headed
        // "User details" with nothing under it would be a statement about the person - it says
        // there is something here you may not have - which is the opposite of what an empty
        // category means.
        if (empty($facts) && empty($actions)) {
            return null;
        }

        return [
            'title' => (string) $category->title,
            'classes' => trim('node_category ' . (string) $category->classes),
            'hasfacts' => !empty($facts),
            'facts' => $facts,
            'hasactions' => !empty($actions),
            'actions' => $actions,
        ];
    }

    /**
     * Build the template context for one node.
     *
     * NEITHER the title NOR the content is escaped, and both must stay that way.
     *
     * The content is pre-built HTML by contract, assembled with html_writer by core (the course
     * list in lib/myprofilelib.php is a whole <ul>) and by every plugin adding a node.
     *
     * So is the title: core passes it through html_writer::link() and html_writer::tag(), neither
     * of which escapes, and plugins rely on that - tool_muloginas renders its whole "Log in as"
     * button and passes it as the node's title, with no content and no url at all
     * (tool/muloginas/lib.php). Escaping it here would print that markup as text. Whoever builds a
     * node owns the escaping of everything in it. Pinned by myprofile_renderer_test.
     *
     * @param node $node The node.
     * @return array The context.
     */
    protected function node_context(node $node): array {
        $url = $node->url;
        $icon = $node->icon;

        $iconhtml = '';
        if (!empty($icon)) {
            // Core calls $this->render($icon) unguarded, which is a fatal error for the plain
            // string the node docblock also allows. Both shapes are accepted here.
            $iconhtml = ($icon instanceof renderable) ? $this->render($icon) : (string) $icon;
        }

        return [
            'name' => (string) $node->name,
            'title' => (string) $node->title,
            'hasurl' => !empty($url),
            'url' => !empty($url) ? $url->out(false) : '',
            'content' => (string) $node->content,
            'hasicon' => ($iconhtml !== ''),
            'iconhtml' => $iconhtml,
            'classes' => trim((string) $node->classes),
        ];
    }
}
