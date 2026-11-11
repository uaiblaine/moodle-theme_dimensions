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
 * The class name is derived by renderer_factory_base::standard_renderer_classnames(): for
 * $PAGE->get_renderer('core_user', 'myprofile') the theme's candidate is
 * theme_dimensions\output\core_user\myprofile_renderer, so a wrong namespace silently falls back
 * to core's renderer.
 *
 * user/profile.php and user/view.php build the same tree through manager::build_tree() and hand
 * it to this renderer, so the site profile and the in-course profile change together. The heading,
 * the user picture and the "About me" description are printed by those scripts before the tree;
 * they are styled in scss/post.scss and their visibility is configuration.
 *
 * Core renders every node of a category into one flat list. Here nodes carrying content become a
 * definition list of facts and nodes that are only a link become a list of actions at the foot of
 * the card; nothing is dropped, invented or moved across categories.
 *
 * Nothing is filtered here, on purpose: the tree has no removal API (a callback can add but never
 * prune), so a renderer could withhold a node, but the profile's privacy is $CFG->hiddenuserfields,
 * which core consults while building the tree, and a second rule here would be a place for the two
 * to disagree.
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

        // Core prints nothing for a category with no nodes, and neither does this: an empty card
        // headed "User details" would suggest something is being withheld.
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
     * Neither the title nor the content is escaped, and both must stay that way: whoever builds a
     * node owns the escaping of everything in it.
     *
     * The content is pre-built HTML by contract (core's course list in lib/myprofilelib.php is a
     * whole <ul>). So is the title: core passes it through html_writer::link() and
     * html_writer::tag(), neither of which escapes, and tool_muloginas passes its whole "Log in as"
     * button as a node's title, with no content and no url. Pinned by myprofile_renderer_test.
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
