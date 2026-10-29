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

namespace theme_dimensions\local\flavourcss;

/**
 * Theme Dimensions - Compiles ONE Boost Union flavour's stylesheet before its first visitor asks for it.
 *
 * Boost Union compiles a flavour's sheet only when a browser requests it (flavours/styles.php), so after
 * every theme cache reset the first visitor of each flavour waits for a whole theme compile. This class
 * does that compile in a CLI process and fills the three stores styles.php reads, under the names it
 * reads them by: the MUC entry, the localcache file and the tempdir fallback. docs/flavourcss/PLAN.md
 * (section 4.2) is the design.
 *
 * Two guards keep it from writing a wrong sheet, which would be worse than a slow one:
 * - the static guard: Boost Union keeps the first flavour record a process reads in a static and never
 *   compares the id again (flavourslib.php, {@see self::BU_RECORD_FUNCTION}), so a second flavour compiled
 *   in the same process gets the first one's look. The caller runs one process per flavour; this class
 *   refuses to compile when the static already holds another flavour.
 * - the stale-write guard: the MUC key carries no revision, so a sheet compiled before a reset and written
 *   after it would outlive the reset. The revisions are read from the database before and after the
 *   compile, and a sheet whose revision moved is discarded.
 *
 * The file names, the cache key and the store sequence are copies of Boost Union's styles.php helpers,
 * which cannot be included (the file runs its request at the top level); {@see \theme_dimensions\local\upstream}
 * keeps their snapshots. The lock is the one styles.php and core's theme/styles.php take for the theme
 * (styles.php:189-190), so a visitor waits for this compile instead of running a second one.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class builder {
    /** @var int The sheet was compiled and stored. */
    const BUILT = 0;

    /** @var int Nothing to build here: designer mode, another site theme, or no such flavour. */
    const SKIPPED = 2;

    /** @var int The sheet for the current revisions already exists. */
    const PRESENT = 3;

    /** @var int Another process held the theme's lock for the whole timeout. */
    const BUSY = 4;

    /** @var int A revision moved during the compile; nothing was written. */
    const STALE = 5;

    /** @var string The theme whose flavour sheets this class builds. */
    const THEME = 'dimensions';

    /**
     * A declaration only this theme's own SCSS produces.
     *
     * Core does not throw when the SCSS fails to compile: it calls debugging() and falls back to the precompiled
     * sheet (theme_config::get_css_content()), which carries none of this theme's rules. A sheet without the
     * marker is that fallback, and storing it would serve the flavour unstyled until the next reset.
     *
     * @var string
     */
    const MARKER = '--dim-ds-surface-page:';

    /** @var string The Boost Union function whose static decides which flavour a compile reads. */
    const BU_RECORD_FUNCTION = 'theme_boost_union_get_flavour_config_item_for_flavourid';

    /** @var int Seconds to wait for the theme's lock, as long as a visitor's request waits in styles.php. */
    const LOCK_TIMEOUT = 120;

    /** @var \Closure Compiles a loaded theme: fn(\core\output\theme_config $theme): string. */
    private \Closure $compiler;

    /** @var \core\lock\lock_factory The factory of the theme's CSS lock. */
    private \core\lock\lock_factory $lockfactory;

    /** @var \Closure Reads which flavour Boost Union's static holds: fn(): ?int. */
    private \Closure $staticflavourid;

    /** @var int Seconds to wait for the lock. */
    private int $locktimeout;

    /**
     * Each collaborator defaults to the real one; tests replace them.
     *
     * @param \Closure|null $compiler Compiles a loaded theme; default theme_config::get_css_content().
     * @param \core\lock\lock_factory|null $lockfactory The lock factory; default core_theme_get_css_content's.
     * @param \Closure|null $staticflavourid Reads Boost Union's static; default {@see self::boost_union_static_flavourid()}.
     * @param int $locktimeout Seconds to wait for the lock.
     */
    public function __construct(
        ?\Closure $compiler = null,
        ?\core\lock\lock_factory $lockfactory = null,
        ?\Closure $staticflavourid = null,
        int $locktimeout = self::LOCK_TIMEOUT
    ) {
        $this->compiler = $compiler ?? fn(\core\output\theme_config $theme): string => $theme->get_css_content();
        $this->lockfactory = $lockfactory ?? \core\lock\lock_config::get_lock_factory('core_theme_get_css_content');
        $this->staticflavourid = $staticflavourid ?? fn(): ?int => self::boost_union_static_flavourid();
        $this->locktimeout = $locktimeout;
    }

    /**
     * Compile one flavour's sheet for the current revisions and store it, unless it is there already.
     *
     * @param int $flavourid The flavour.
     * @param string $direction 'ltr' or 'rtl'.
     * @return int One of BUILT, SKIPPED, PRESENT, BUSY, STALE.
     * @throws \coding_exception Outside the CLI, for an unknown direction, or when Boost Union's static holds another flavour.
     * @throws \moodle_exception When the compiled sheet lacks the theme's own rules.
     */
    public function build(int $flavourid, string $direction = 'ltr'): int {
        global $CFG, $DB;

        // The flavour global would re-flavour a page being served.
        if (!CLI_SCRIPT) {
            throw new \coding_exception('A flavour sheet is built from the command line only.');
        }
        if ($direction !== 'ltr' && $direction !== 'rtl') {
            throw new \coding_exception('Unknown direction: ' . $direction);
        }
        // Designer mode turns every theme cache off (revision -1), so there is nothing to store.
        if (!empty($CFG->themedesignermode) || $CFG->theme !== self::THEME) {
            return self::SKIPPED;
        }
        if (!$DB->record_exists('theme_boost_union_flavours', ['id' => $flavourid])) {
            return self::SKIPPED;
        }
        $held = ($this->staticflavourid)();
        if ($held !== null && $held !== $flavourid) {
            throw new \coding_exception(
                "Boost Union's flavour record here is flavour {$held}; flavour {$flavourid} needs its own process."
            );
        }

        [$rev, $subrev] = self::revisions();
        if (file_exists(self::sheet_path($rev, $subrev, $flavourid, $direction))) {
            return self::PRESENT;
        }

        $lock = $this->lockfactory->get_lock(self::THEME, $this->locktimeout);
        if (!$lock) {
            return self::BUSY;
        }
        try {
            // A visitor may have built it, or a reset moved the revisions, while this process waited.
            [$rev, $subrev] = self::revisions();
            $sheet = self::sheet_path($rev, $subrev, $flavourid, $direction);
            if (file_exists($sheet)) {
                return self::PRESENT;
            }

            [$theme, $css] = $this->compile($flavourid, $direction);
            if (!str_contains($css, self::MARKER)) {
                throw new \moodle_exception('flavourcssfailed', 'theme_dimensions', '', $flavourid);
            }
            if (self::revisions() !== [$rev, $subrev]) {
                return self::STALE;
            }
            self::store($theme, $rev, $subrev, $flavourid, $direction, $css);

            return self::BUILT;
        } finally {
            $lock->release();
        }
    }

    /**
     * The flavour whose record Boost Union's static holds in this process, or null while it holds none.
     *
     * Read through reflection, so a Boost Union that renames the function fails here, closed, rather than
     * compiling unguarded. A Boost Union that keys the static by flavour id answers null for good.
     *
     * @return int|null The flavour id, or null.
     * @throws \ReflectionException When Boost Union no longer has the function.
     */
    public static function boost_union_static_flavourid(): ?int {
        global $CFG;
        require_once($CFG->dirroot . '/theme/boost_union/flavours/flavourslib.php');

        $statics = (new \ReflectionFunction(self::BU_RECORD_FUNCTION))->getStaticVariables();
        $record = $statics['flavourrecord'] ?? null;

        return is_object($record) ? (int) $record->id : null;
    }

    /**
     * Where Boost Union's styles.php looks for the sheet first.
     *
     * @param int $rev The global theme revision.
     * @param int $subrev The theme's sub-revision.
     * @param int $flavourid The flavour.
     * @param string $direction 'ltr' or 'rtl'.
     * @return string The absolute path.
     */
    public static function sheet_path(int $rev, int $subrev, int $flavourid, string $direction): string {
        global $CFG;

        return "{$CFG->localcachedir}/theme/{$rev}/" . self::THEME . '/css/'
            . self::filename(self::type($direction), $subrev, $flavourid);
    }

    /**
     * The MUC key of a flavour's sheet in core/postprocessedcss.
     *
     * A copy of Boost Union's theme_boost_union_flavour_get_css_cache_key() (flavours/styles.php), taking the
     * direction instead of a theme: SVG is always forced on, so the 'nosvg_' prefix never applies.
     *
     * @param int $flavourid The flavour.
     * @param string $direction 'ltr' or 'rtl'.
     * @return string The key.
     */
    public static function cache_key(int $flavourid, string $direction): string {
        return self::THEME . '_' . $flavourid . '_' . $direction;
    }

    /**
     * The revisions as the database holds them now.
     *
     * Read from the database rather than $CFG: a long cron process keeps the $CFG of its start, and the
     * stale-write guard compares against what a reset in another process has just written. Empty reads 1,
     * as theme_get_revision() and theme_get_sub_revision_for_theme() do.
     *
     * @return int[] The global revision and the theme's sub-revision.
     */
    private static function revisions(): array {
        global $DB;

        $rev = (int) $DB->get_field('config', 'value', ['name' => 'themerev']);
        $subrev = (int) $DB->get_field('config_plugins', 'value', ['plugin' => 'theme_' . self::THEME, 'name' => 'themerev']);

        return [$rev ?: 1, $subrev ?: 1];
    }

    /**
     * Compile the theme with the flavour applied, as Boost Union's styles.php does.
     *
     * @param int $flavourid The flavour.
     * @param string $direction 'ltr' or 'rtl'.
     * @return array The loaded theme and the sheet.
     */
    private function compile(int $flavourid, string $direction): array {
        // Boost Union's pre and extra SCSS callbacks read the flavour from this global (lib.php), set by styles.php.
        $GLOBALS['themeboostunionappliedflavour'] = $flavourid;
        try {
            $theme = \core\output\theme_config::load(self::THEME);
            $theme->force_svg_use(true);
            $theme->set_rtl_mode($direction === 'rtl');
            $css = ($this->compiler)($theme);
        } finally {
            unset($GLOBALS['themeboostunionappliedflavour']);
        }

        return [$theme, $css];
    }

    /**
     * The sheet type styles.php names a direction by.
     *
     * @param string $direction 'ltr' or 'rtl'.
     * @return string 'all' or 'all-rtl'.
     */
    private static function type(string $direction): string {
        return $direction === 'rtl' ? 'all-rtl' : 'all';
    }

    /**
     * Store a sheet where Boost Union's styles.php reads it, and remove the older revisions.
     *
     * A copy of theme_boost_union_flavour_styles_generate_and_store() (flavours/styles.php), modified: the
     * sheet arrives compiled, so the MUC entry is written rather than read, and the directory is derived here;
     * SVG is always forced on.
     *
     * @param \core\output\theme_config $theme The loaded theme.
     * @param int $rev The global theme revision.
     * @param int $subrev The theme's sub-revision.
     * @param int $flavourid The flavour.
     * @param string $direction 'ltr' or 'rtl'.
     * @param string $css The sheet.
     * @return void
     */
    private static function store(
        \core\output\theme_config $theme,
        int $rev,
        int $subrev,
        int $flavourid,
        string $direction,
        string $css
    ): void {
        global $CFG;
        require_once("{$CFG->libdir}/csslib.php");
        require_once("{$CFG->libdir}/filelib.php");

        \cache::make('core', 'postprocessedcss')->set(self::cache_key($flavourid, $direction), $css);

        make_localcache_directory('theme', false);
        css_store_css($theme, self::sheet_path($rev, $subrev, $flavourid, $direction), $css);

        // The unversioned fallback styles.php can send while a sheet compiles.
        $fallbacksheet = make_temp_directory('theme/' . self::THEME) . '/'
            . self::filename(self::type($direction), 0, $flavourid);
        css_store_css($theme, $fallbacksheet, $css);

        // Delete older revisions from localcache.
        foreach (glob("{$CFG->localcachedir}/theme/*", GLOB_ONLYDIR) as $localcachedir) {
            $cachedrev = [];
            preg_match("/\/theme\/([0-9]+)$/", $localcachedir, $cachedrev);
            $cachedrev = isset($cachedrev[1]) ? intval($cachedrev[1]) : 0;
            if ($cachedrev > 0 && $cachedrev < $rev) {
                fulldelete($localcachedir);
            }
        }

        // Delete older theme sub-revision CSS from localcache.
        foreach (glob("{$CFG->localcachedir}/theme/{$rev}/" . self::THEME . '/css/*.css') as $subrevfile) {
            $cachedsubrev = [];
            preg_match("/_([0-9]+)_([0-9]+)\.([0-9]+\.)?css$/", $subrevfile, $cachedsubrev);
            $cachedsubrev = isset($cachedsubrev[1]) ? intval($cachedsubrev[1]) : 0;
            if ($cachedsubrev > 0 && $cachedsubrev < $subrev) {
                fulldelete($subrevfile);
            }
        }
    }

    /**
     * The file name styles.php gives a sheet.
     *
     * A copy of theme_boost_union_flavour_styles_get_filename() (flavours/styles.php), with SVG always on.
     *
     * @param string $type The sheet type.
     * @param int $themesubrev The theme's sub-revision; 0 for the unversioned fallback.
     * @param int $flavourid The flavour.
     * @return string The file name.
     */
    private static function filename(string $type, int $themesubrev, int $flavourid): string {
        $filename = $type;
        $filename .= ($themesubrev > 0) ? "_{$themesubrev}" : '';
        $filename .= ($flavourid > 0) ? "_{$flavourid}" : '';

        return "{$filename}.css";
    }
}
