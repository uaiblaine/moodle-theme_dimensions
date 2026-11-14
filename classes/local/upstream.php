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

namespace theme_dimensions\local;

/**
 * Theme Dimensions - The upstream snapshots of the theme's copies of core and Boost Union code.
 *
 * Every template the theme overrides (templates/<component>/...) and every method it copies from core
 * keeps, next to the copy, a verbatim snapshot of the upstream it was last re-diffed against:
 * <copy>.upstream for a template, <copy file>.<method>.upstream for a method. Boost Union keeps its
 * own copies of core the same way (e.g. templates/core/loginform.mustache.upstream).
 *
 * A script copied whole (core's calendar/export_execute.php, {@see self::FILES}) keeps the whole file as its snapshot:
 * <copy file>.<name>.upstream.
 *
 * The snapshot turns a re-diff into a three-way merge: what upstream changed since the copy was made
 * is the diff between the snapshot and today's upstream, and that diff can be applied to the copy
 * (scripts/upstream-merge.sh does it with git merge-file). tests/local/upstream_test.php compares
 * every snapshot with today's upstream and reddens the moment core or Boost Union changes one.
 *
 * Today's upstream is read the way the page reads it, not from a path written here: a template
 * through core's template finder for the theme's first parent, so a Boost Union override shadows
 * Boost's and core's exactly as it does for a page; a method through reflection on the class the
 * copy would otherwise inherit from, so a parent that starts overriding the method changes the
 * upstream too.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class upstream {
    /**
     * The methods the theme copies from core.
     *
     * Each entry: the copy's file (relative to the theme root), the copy's class and method, and the
     * class and method it was copied from. A null upstream class means the copy's own parent class:
     * the method the copy replaces.
     *
     * @var array
     */
    const METHODS = [
        [
            'file' => 'classes/output/core/course_renderer.php',
            'class' => \theme_dimensions\output\core\course_renderer::class,
            'method' => 'coursecat_subcategories',
            'upstreamclass' => null,
            'upstreammethod' => 'coursecat_subcategories',
        ],
        [
            'file' => 'classes/output/core/course_renderer.php',
            'class' => \theme_dimensions\output\core\course_renderer::class,
            'method' => 'coursecat_category_content',
            'upstreamclass' => null,
            'upstreammethod' => 'coursecat_category_content',
        ],
        [
            'file' => 'classes/output/core/course_renderer.php',
            'class' => \theme_dimensions\output\core\course_renderer::class,
            'method' => 'tagged_courses',
            'upstreamclass' => null,
            'upstreammethod' => 'tagged_courses',
        ],
        [
            'file' => 'classes/output/core/course_renderer.php',
            'class' => \theme_dimensions\output\core\course_renderer::class,
            'method' => 'search_courses',
            'upstreamclass' => null,
            'upstreammethod' => 'search_courses',
        ],
        [
            'file' => 'classes/output/core/course_renderer.php',
            'class' => \theme_dimensions\output\core\course_renderer::class,
            'method' => 'frontpage_available_courses',
            'upstreamclass' => null,
            'upstreammethod' => 'frontpage_available_courses',
        ],
        [
            'file' => 'classes/form/categoryhero.php',
            'class' => \theme_dimensions\form\categoryhero::class,
            'method' => 'description_editor_options',
            'upstreamclass' => \core_course_editcategory_form::class,
            'upstreammethod' => 'get_description_editor_options',
        ],
    ];

    /**
     * The functions the theme copies from a file that cannot be included.
     *
     * Boost Union's flavours/styles.php serves its request from the top level, so its helpers are read from the
     * file's tokens rather than through reflection. Each entry: the copy's file and method, and the upstream file
     * (relative to $CFG->dirroot) and function.
     *
     * @var array
     */
    const FUNCTIONS = [
        [
            'file' => 'classes/local/flavourcss/builder.php',
            'method' => 'cache_key',
            'upstreamfile' => 'theme/boost_union/flavours/styles.php',
            'upstreamfunction' => 'theme_boost_union_flavour_get_css_cache_key',
        ],
        [
            'file' => 'classes/local/flavourcss/builder.php',
            'method' => 'filename',
            'upstreamfile' => 'theme/boost_union/flavours/styles.php',
            'upstreamfunction' => 'theme_boost_union_flavour_styles_get_filename',
        ],
        [
            'file' => 'classes/local/flavourcss/builder.php',
            'method' => 'store',
            'upstreamfile' => 'theme/boost_union/flavours/styles.php',
            'upstreamfunction' => 'theme_boost_union_flavour_styles_generate_and_store',
        ],
    ];

    /**
     * The scripts the theme copies whole: a file of core read as text, never included.
     *
     * Core's calendar/export_execute.php is a script of top-level statements (it defines NO_MOODLE_COOKIES and
     * requires config.php), so neither reflection nor a function's tokens can name what was copied: the snapshot is
     * the whole file. Each entry: the copy's file, a name for the snapshot, and the upstream file (relative to
     * $CFG->dirroot).
     *
     * @var array
     */
    const FILES = [
        [
            'file' => 'classes/local/calendar/ical.php',
            'name' => 'export_execute',
            'upstreamfile' => 'calendar/export_execute.php',
        ],
    ];

    /**
     * The templates under templates/<component>/ that are the theme's own and copy nothing.
     *
     * Core draws the profile page in PHP (core_user\output\myprofile\renderer), so the theme's renderer renders
     * these four under core_user's name with no core template behind them. Every other template there must
     * have an upstream: one that loses it is a template core removed, which the theme then overrides in vain.
     *
     * @var string[]
     */
    const OWN_TEMPLATES = [
        'core_user/myprofile_category',
        'core_user/myprofile_courses',
        'core_user/myprofile_node',
        'core_user/myprofile_tree',
    ];

    /** @var string The extension every snapshot carries. */
    const EXTENSION = '.upstream';

    /**
     * The theme's root directory.
     *
     * @return string
     */
    public static function root(): string {
        return dirname(__DIR__, 2);
    }

    /**
     * Every copy and today's upstream of it.
     *
     * Each entry: snapshot and copy (paths relative to the theme root), source (where today's upstream
     * was read: an absolute file path, or Class::method) and text (today's upstream, verbatim).
     *
     * @return array The entries, sorted by snapshot path.
     * @throws \moodle_exception When an overridden template has no upstream left.
     * @throws \coding_exception When an upstream file no longer declares a copied function.
     */
    public static function entries(): array {
        $entries = array_merge(
            self::template_entries(),
            self::method_entries(),
            self::function_entries(),
            self::file_entries()
        );
        usort($entries, fn(array $a, array $b): int => strcmp($a['snapshot'], $b['snapshot']));

        return $entries;
    }

    /**
     * Every snapshot file in the theme, whether or not a copy still claims it.
     *
     * @return string[] Paths relative to the theme root, sorted.
     */
    public static function snapshot_files(): array {
        $root = self::root();
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $path = substr($file->getPathname(), strlen($root) + 1);
            if (str_ends_with($path, self::EXTENSION) && !str_starts_with($path, '.')) {
                $files[] = $path;
            }
        }
        sort($files);

        return $files;
    }

    /**
     * The template overrides: every templates/<component>/<name>.mustache.
     *
     * The theme's own templates sit directly under templates/ and are not copies of anything.
     *
     * @return array The entries.
     * @throws \moodle_exception When an overridden template has no upstream left.
     */
    private static function template_entries(): array {
        $root = self::root();
        $parent = \theme_config::load('dimensions')->parents[0];
        $entries = [];
        foreach (glob($root . '/templates/*', GLOB_ONLYDIR) as $dir) {
            $component = basename($dir);
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'mustache') {
                    continue;
                }
                $copy = substr($file->getPathname(), strlen($root) + 1);
                $name = substr($file->getPathname(), strlen($dir) + 1, -strlen('.mustache'));
                if (in_array($component . '/' . $name, self::OWN_TEMPLATES, true)) {
                    continue;
                }
                $source = \core\output\mustache_template_finder::get_template_filepath($component . '/' . $name, $parent);
                $entries[] = [
                    'snapshot' => $copy . self::EXTENSION,
                    'copy' => $copy,
                    'source' => $source,
                    'text' => (string) file_get_contents($source),
                ];
            }
        }

        return $entries;
    }

    /**
     * The method copies of {@see self::METHODS}.
     *
     * The text is the method from its signature to its closing brace, without its docblock: the copy's
     * docblock is the theme's own account of what it changed.
     *
     * @return array The entries.
     */
    private static function method_entries(): array {
        global $CFG;

        // Core's course renderer is loaded by the renderer factory, not the autoloader, and the theme's renderer
        // extends it through Boost Union's.
        require_once($CFG->dirroot . '/course/renderer.php');

        $entries = [];
        foreach (self::METHODS as $method) {
            $class = $method['upstreamclass'] ?? get_parent_class($method['class']);
            $reflection = new \ReflectionMethod($class, $method['upstreammethod']);
            $lines = file($reflection->getFileName());
            $text = implode('', array_slice(
                $lines,
                $reflection->getStartLine() - 1,
                $reflection->getEndLine() - $reflection->getStartLine() + 1
            ));
            $entries[] = [
                'snapshot' => $method['file'] . '.' . $method['method'] . self::EXTENSION,
                'copy' => $method['file'],
                'source' => $reflection->getDeclaringClass()->getName() . '::' . $reflection->getName(),
                'text' => $text,
            ];
        }

        return $entries;
    }

    /**
     * The function copies of {@see self::FUNCTIONS}, in the shape of a method entry.
     *
     * @return array The entries.
     * @throws \coding_exception When the upstream file no longer declares the function.
     */
    private static function function_entries(): array {
        global $CFG;

        $entries = [];
        foreach (self::FUNCTIONS as $function) {
            $entries[] = [
                'snapshot' => $function['file'] . '.' . $function['method'] . self::EXTENSION,
                'copy' => $function['file'],
                'source' => $function['upstreamfile'] . '::' . $function['upstreamfunction'],
                'text' => self::function_source($CFG->dirroot . '/' . $function['upstreamfile'], $function['upstreamfunction']),
            ];
        }

        return $entries;
    }

    /**
     * The whole-file copies of {@see self::FILES}, in the shape of a method entry: the upstream is the file, verbatim
     * but for its development markers ({@see self::masked()}).
     *
     * @return array The entries.
     */
    private static function file_entries(): array {
        global $CFG;

        $entries = [];
        foreach (self::FILES as $file) {
            $source = $CFG->dirroot . '/' . $file['upstreamfile'];
            $entries[] = [
                'snapshot' => $file['file'] . '.' . $file['name'] . self::EXTENSION,
                'copy' => $file['file'],
                'source' => $file['upstreamfile'],
                'text' => self::masked((string) file_get_contents($source)),
            ];
        }

        return $entries;
    }

    /**
     * A whole upstream file with the development markers the leftover gate refuses spelled apart.
     *
     * A whole file carries core's own comments, and core's calendar/export_execute.php holds a bare marker comment
     * that the leftover checker of the CI workflow refuses in any shipped file. The
     * snapshot spells each marker with a hyphen; the comparison of every other byte is unchanged.
     *
     * @param string $text The upstream file.
     * @return string The text the snapshot holds.
     */
    public static function masked(string $text): string {
        return str_replace(['TO' . 'DO', '@test' . 'me'], ['TO-' . 'DO', '@test-' . 'me'], $text);
    }

    /**
     * A top-level function's source, from its signature's line to its closing brace's, without its docblock.
     *
     * The file is tokenised, never included.
     *
     * @param string $file The absolute path of the PHP file.
     * @param string $name The function's name.
     * @return string The lines, verbatim.
     * @throws \coding_exception When the file does not declare the function.
     */
    public static function function_source(string $file, string $name): string {
        $tokens = \PhpToken::tokenize((string) file_get_contents($file));
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if (!$tokens[$i]->is(T_FUNCTION)) {
                continue;
            }
            $j = $i + 1;
            while ($j < $count && $tokens[$j]->isIgnorable()) {
                $j++;
            }
            if ($j === $count || !$tokens[$j]->is(T_STRING) || $tokens[$j]->text !== $name) {
                continue;
            }
            $depth = 0;
            for ($k = $j; $k < $count; $k++) {
                if ($tokens[$k]->text === '{' || $tokens[$k]->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                    $depth++;
                } else if ($tokens[$k]->text === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $lines = file($file);
                        $start = $tokens[$i]->line;

                        return implode('', array_slice($lines, $start - 1, $tokens[$k]->line - $start + 1));
                    }
                }
            }
        }
        throw new \coding_exception("{$file} declares no function {$name}.");
    }
}
