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
 * Theme Dimensions - The category appearance form
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\form;

use local_unlistedcourses\category_access;
use theme_dimensions\local\category\branding;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\unavailable_exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * The editor of a category's hero: a plain moodleform, complete without JavaScript.
 *
 * Not a dynamic_form: the page carries six file managers and must work with JavaScript off.
 * Four header sections (description, appearance, colours, public page) with short forms left on
 * (setDisableShortforms(false)), so core supplies the accordion, expand-all and the expansion of a
 * section holding a validation error; no JavaScript relocates any row.
 *
 * The colours are two free hex fields. The JavaScript module adds a swatch, a live preview and a
 * contrast panel, but the guard is this form and {@see branding::validate()}: a value that is not
 * a hex colour and a pair under {@see branding::FLOOR} are refused. While "own colours" is
 * unticked the fields are disabled, show the inherited pair, and are neither validated nor stored.
 *
 * The description section edits core's own coursecat description with core's editor options; the
 * page saves it through core_course_category::update(), so core's category page keeps working on
 * the same data.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class categoryhero extends \moodleform {
    /** @var string Component name for get_string calls. */
    private const COMPONENT = 'theme_dimensions';

    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;

        $categoryid = (int) $this->_customdata['categoryid'];

        $mform->addElement('hidden', 'id', $categoryid);
        $mform->setType('id', PARAM_INT);

        /* Core's own category description, never stored in this plugin's table: the page saves it
           through core_course_category::update(), which fires course_category_updated and purges
           core's caches. */
        $mform->addElement('header', 'descriptionheader', get_string('categoryhero_descriptionheader', self::COMPONENT));
        $mform->setExpanded('descriptionheader', true);
        $mform->addElement(
            'editor',
            'description_editor',
            get_string('description'),
            null,
            self::description_editor_options($categoryid)
        );
        $mform->setType('description_editor', PARAM_RAW);

        $mform->addElement('header', 'appearanceheader', get_string('categoryhero_appearance', self::COMPONENT));
        $mform->setExpanded('appearanceheader', true);

        $mform->addElement('select', 'layout', get_string('categoryhero_layout', self::COMPONENT), self::layout_options());
        $mform->setType('layout', PARAM_ALPHA);
        $mform->setDefault('layout', branding::DEFAULT_LAYOUT);

        $mform->addElement(
            'filemanager',
            'heroimage',
            get_string('categoryhero_image', self::COMPONENT),
            null,
            branding::filemanager_options()
        );
        $mform->addHelpButton('heroimage', 'categoryhero_image', self::COMPONENT);

        /* The card's cover, its cover video and the three crests: one file manager per area of
           branding::single_file_areas(), named after its file area. The area name is the contract:
           form_data() prepares and save() reads the draft under it, so a renamed element would
           silently store nothing.

           A file manager with maxfiles 1, not a filepicker: a filepicker cannot remove its file.

           The video's extension list is only a name check; its bytes are read in validation().
           The crests take the landing page's limit and file types (SVG included, see
           branding::resolve()), so a crest allowed on a course is allowed on its category. */
        foreach (branding::single_file_areas() as $area => $options) {
            $key = self::file_area_string($area);
            $mform->addElement('filemanager', $area, get_string($key, self::COMPONENT), null, $options);
            $mform->addHelpButton($area, $key, self::COMPONENT);
        }

        /* The card's own text in its parent's grid, in place of the description's excerpt (the hero
           keeps the description). Capped at the excerpt's length, so a card with its own text is
           never longer than one without. */
        $mform->addElement('text', 'cardsummary', get_string('categoryhero_cardsummary', self::COMPONENT), [
            'size' => 64,
            'maxlength' => branding::CARDSUMMARY_MAXLENGTH,
        ]);
        $mform->addHelpButton('cardsummary', 'categoryhero_cardsummary', self::COMPONENT);
        $mform->setType('cardsummary', PARAM_TEXT);
        $mform->addRule(
            'cardsummary',
            get_string('maximumchars', '', branding::CARDSUMMARY_MAXLENGTH),
            'maxlength',
            branding::CARDSUMMARY_MAXLENGTH,
            'client'
        );

        $mform->addElement('text', 'tagline', get_string('categoryhero_tagline', self::COMPONENT), [
            'size' => 64,
            'maxlength' => branding::TAGLINE_MAXLENGTH,
        ]);
        $mform->addHelpButton('tagline', 'categoryhero_tagline', self::COMPONENT);
        $mform->setType('tagline', PARAM_TEXT);
        $mform->addRule(
            'tagline',
            get_string('maximumchars', '', branding::TAGLINE_MAXLENGTH),
            'maxlength',
            branding::TAGLINE_MAXLENGTH,
            'client'
        );

        /* The sort a listing address naming none gets on this category; the choices are the
           address's own vocabulary (criteria::SORTS). */
        $mform->addElement(
            'select',
            'defaultsort',
            get_string('categoryhero_defaultsort', self::COMPONENT),
            self::defaultsort_options()
        );
        $mform->addHelpButton('defaultsort', 'categoryhero_defaultsort', self::COMPONENT);
        $mform->setType('defaultsort', PARAM_ALPHA);
        $mform->setDefault('defaultsort', '');

        /* The colours get a section of their own so the stylesheet can lay this one fieldset out
           as a two-column grid (fields and overlay, then the contrast panel the module mounts):
           the rows stay where core drew them. A field belongs to the section it is declared after,
           so the order of these calls is a layout decision. The overlay's hideIf on `layout` may
           cross sections: M.form.initFormDependencies works over the whole form. */
        $mform->addElement('header', 'coloursheader', get_string('categoryhero_colours', self::COMPONENT));
        $mform->setExpanded('coloursheader', true);

        /* Unticked, the category takes the pair of its nearest ancestor that has one
           (branding::colours()), and the two fields below are disabled rather than hidden: they
           show the inherited pair form_data() put there, so ticking the box starts from what the
           page draws. The static note names where the pair comes from. */
        $mform->addElement(
            'advcheckbox',
            'owncolours',
            get_string('categoryhero_owncolours', self::COMPONENT),
            get_string('categoryhero_owncolours_label', self::COMPONENT),
            null,
            [0, 1]
        );
        $mform->addHelpButton('owncolours', 'categoryhero_owncolours', self::COMPONENT);
        $mform->setType('owncolours', PARAM_INT);
        $mform->setDefault('owncolours', 0);
        $mform->addElement('static', 'colourinheritance', '', self::inheritance_note($categoryid));

        /* PARAM_RAW and not PARAM_TEXT: PARAM_TEXT strips tags, so a hex value wrapped in markup
           would arrive as a colour that parses, and a colour field has to say no rather than
           guess. validation() narrows it, through branding::hex(). */
        $mform->addElement('text', 'bgcolour', get_string('categoryhero_bgcolour', self::COMPONENT), [
            'size' => 9,
            'maxlength' => 7,
        ]);
        $mform->addHelpButton('bgcolour', 'categoryhero_bgcolour', self::COMPONENT);
        $mform->setType('bgcolour', PARAM_RAW);
        $mform->setDefault('bgcolour', branding::DEFAULT_BG);

        $mform->addElement('text', 'inkcolour', get_string('categoryhero_inkcolour', self::COMPONENT), [
            'size' => 9,
            'maxlength' => 7,
        ]);
        $mform->addHelpButton('inkcolour', 'categoryhero_inkcolour', self::COMPONENT);
        $mform->setType('inkcolour', PARAM_RAW);
        $mform->setDefault('inkcolour', branding::DEFAULT_INK);
        $mform->disabledIf('bgcolour', 'owncolours', 'notchecked');
        $mform->disabledIf('inkcolour', 'owncolours', 'notchecked');

        $mform->addElement('select', 'overlay', get_string('categoryhero_overlay', self::COMPONENT), self::overlay_options());
        $mform->addHelpButton('overlay', 'categoryhero_overlay', self::COMPONENT);
        $mform->setType('overlay', PARAM_INT);
        $mform->setDefault('overlay', branding::DEFAULT_OVERLAY);
        $mform->hideIf('overlay', 'layout', 'neq', branding::LAYOUT_IMAGE);

        /* The stored pair on a band with the category's name, server-rendered so it is right with
           JavaScript off. An `html` element, not a static one: QuickForm appends it raw inside this
           fieldset's container (HTML_QuickForm_html::accept() -> renderHtml()), the box the grid is
           drawn on, with no label column. */
        $mform->addElement('html', self::pair_preview($categoryid));

        $mform->addElement('header', 'publicheader', get_string('categoryhero_public', self::COMPONENT));

        /* The short public description (what a share preview and a search result show), above the
           page's rich text. The maxlength attribute stops typing at the limit; validation() refuses
           past it for a browser that ignores the attribute. */
        $mform->addElement('text', 'publicsummary', get_string('categoryhero_publicsummary', self::COMPONENT), [
            'size' => 80,
            'maxlength' => branding::PUBLICSUMMARY_MAXLENGTH,
        ]);
        $mform->addHelpButton('publicsummary', 'categoryhero_publicsummary', self::COMPONENT);
        $mform->setType('publicsummary', PARAM_TEXT);

        $mform->addElement(
            'editor',
            'publicdescription_editor',
            get_string('categoryhero_publictext', self::COMPONENT),
            null,
            self::publictext_editor_options()
        );
        $mform->addHelpButton('publicdescription_editor', 'categoryhero_publictext', self::COMPONENT);
        $mform->setType('publicdescription_editor', PARAM_RAW);

        $this->add_action_buttons();
        $mform->setDisableShortforms(false);
    }

    /**
     * Server-side validation: choices, colours and their contrast, the cover video, lengths, public text addresses.
     *
     * @param array $data The submitted data.
     * @param array $files The submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!in_array($data['layout'] ?? '', branding::LAYOUTS, true)) {
            $errors['layout'] = get_string('categoryhero_invalidchoice', self::COMPONENT);
        }
        if (branding::sort_choice((string) ($data['defaultsort'] ?? '')) === null) {
            $errors['defaultsort'] = get_string('categoryhero_invalidchoice', self::COMPONENT);
        }
        /* Colours are validated only when they are the category's own: unticked, the fields are
           disabled and post nothing, so a refusal would name a value the manager cannot edit. */
        $owncolours = !empty($data['owncolours']);
        $bgcolour = $owncolours ? branding::hex((string) ($data['bgcolour'] ?? '')) : branding::DEFAULT_BG;
        if ($bgcolour === null) {
            $errors['bgcolour'] = get_string('categoryhero_invalidcolour', self::COMPONENT);
        }
        $inkcolour = $owncolours ? branding::hex((string) ($data['inkcolour'] ?? '')) : branding::DEFAULT_INK;
        if ($inkcolour === null) {
            $errors['inkcolour'] = get_string('categoryhero_invalidcolour', self::COMPONENT);
        }
        /* The contrast floor is checked only on a pair that parsed: a ratio against a default
           standing in for an invalid value would be a number the person cannot act on. */
        if ($bgcolour !== null && $inkcolour !== null) {
            $ratio = branding::contrast($bgcolour, $inkcolour);
            if ($ratio < branding::FLOOR) {
                $errors['inkcolour'] = get_string(
                    'categoryhero_contrastfloor',
                    self::COMPONENT,
                    (object) [
                        'ratio' => branding::format_ratio($ratio),
                        'floor' => branding::format_ratio(branding::FLOOR),
                    ]
                );
            }
        }
        /* The raw value, not a cast one: with 0 in the vocabulary, (int) maps the empty string a
           select hands back for an unknown choice onto "Transparent". */
        if (branding::overlay($data['overlay'] ?? null) === null) {
            $errors['overlay'] = get_string('categoryhero_invalidchoice', self::COMPONENT);
        }
        /* A file that is not a video is refused here, on its field, rather than stored and then
           silently withheld by the file route. The route and the cached payload refuse it as well,
           because this form is not the only way into the file area. */
        $videoerror = self::covervideo_error($data[branding::AREA_COVERVIDEO] ?? 0);
        if ($videoerror !== null) {
            $errors[branding::AREA_COVERVIDEO] = $videoerror;
        }
        if (\core_text::strlen(trim((string) ($data['tagline'] ?? ''))) > branding::TAGLINE_MAXLENGTH) {
            $errors['tagline'] = get_string('maximumchars', '', branding::TAGLINE_MAXLENGTH);
        }
        if (\core_text::strlen(trim((string) ($data['cardsummary'] ?? ''))) > branding::CARDSUMMARY_MAXLENGTH) {
            $errors['cardsummary'] = get_string('maximumchars', '', branding::CARDSUMMARY_MAXLENGTH);
        }
        /* The public page text is link-only; a paste or a drag-and-drop can still leave a draft,
           data: or blob: address in it, which is refused here rather than stored as a picture
           nobody else can see. See branding::UNSERVABLE_ADDRESS. */
        $publictext = (string) ($data['publicdescription_editor']['text'] ?? '');
        if (branding::holds_unservable_address($publictext)) {
            $errors['publicdescription_editor'] = get_string('categoryhero_publictext_nofiles', self::COMPONENT);
        }
        if (\core_text::strlen(trim((string) ($data['publicsummary'] ?? ''))) > branding::PUBLICSUMMARY_MAXLENGTH) {
            $errors['publicsummary'] = get_string(
                'categoryhero_publicsummary_toolong',
                self::COMPONENT,
                branding::PUBLICSUMMARY_MAXLENGTH
            );
        }
        return $errors;
    }

    /**
     * The public page text editor's options: its media controls hand back an address and nothing else.
     *
     * Every value here is load-bearing. Core's multimedia dialogue cannot open on an editor without
     * a draft area: `lib/form/editor.php` builds the file-picker options only when `maxfiles != 0`,
     * and `tiny_media`'s embed dialogue reads them unguarded (`embed/embed.js`, the constructor),
     * where its image dialogue tests `typeof options !== 'undefined'` first. So the editor gets a
     * draft area, and the rest of the set makes it unusable:
     *
     * - `maxfiles => 1` builds the file-picker options, so the dialogue opens.
     * - `return_types => FILE_EXTERNAL` keeps only repositories that return an external address,
     *   dropping `upload`, so neither dialogue offers a drop zone. Exception: the media manager
     *   (`tiny/plugins/media/manage.php`) strips FILE_EXTERNAL, leaving 0, which
     *   `MoodleQuickForm_filemanager` reads as empty and replaces with
     *   FILE_INTERNAL | FILE_REFERENCE | FILE_CONTROLLED_LINK, so that one manager keeps an upload.
     * - `maxbytes => 1` and `areamaxbytes => 1` refuse whatever still reaches an upload (that
     *   manager, a pasted or dragged image through `editor_tiny/uploader`) before a byte is stored.
     *   `areamaxbytes` is the half that matters: holders of `moodle/course:ignorefilesizelimits`
     *   are exempt from `maxbytes` alone.
     *
     * The draft area is never saved by {@see branding::save()}, so a file that reached it stays an
     * orphaned draft for core's cleanup. {@see branding::holds_unservable_address()} refuses (rather
     * than strips) a draft, `blob:` or `data:` address: {@see validation()} reports
     * `categoryhero_publictext_nofiles` and {@see branding::validate()} throws for other callers.
     *
     * @return array The options the editor element takes.
     */
    public static function publictext_editor_options(): array {
        return [
            'maxfiles' => 1,
            'maxbytes' => 1,
            'areamaxbytes' => 1,
            'return_types' => FILE_EXTERNAL,
        ];
    }

    /**
     * The language string key of one single-file area's label and its help button.
     *
     * A literal per area rather than a composed name, so every key can be found by searching for it.
     * An area with no entry here is a programming error and throws, rather than rendering a row
     * labelled with a missing-string notice.
     *
     * @param string $area One of the areas of {@see branding::single_file_areas()}.
     * @return string The key both packs carry, for the label and for `<key>_help`.
     * @throws \coding_exception For an area this form draws no row for.
     */
    private static function file_area_string(string $area): string {
        return match ($area) {
            branding::AREA_COVER => 'categoryhero_cover',
            branding::AREA_COVERVIDEO => 'categoryhero_covervideo',
            branding::AREA_BADGES[0] => 'categoryhero_badge1',
            branding::AREA_BADGES[1] => 'categoryhero_badge2',
            branding::AREA_BADGES[2] => 'categoryhero_badge3',
            default => throw new \coding_exception('No label for the file area ' . $area),
        };
    }

    /**
     * What is wrong with the cover video a file manager has just posted, or null when nothing is.
     *
     * A file manager posts the id of a draft area, not a file, so $files is empty in validation()
     * and the bytes are read from that draft area. A draft id of zero (no element submitted) and an
     * empty draft area (the film removed) are not refusals.
     *
     * @param mixed $draftitemid The draft area the file manager submitted, 0 when there was none.
     * @return string|null The message for the field, or null.
     */
    private static function covervideo_error(mixed $draftitemid): ?string {
        global $USER;

        $draftitemid = (int) $draftitemid;
        if (!$draftitemid) {
            return null;
        }
        $files = get_file_storage()->get_area_files(
            \core\context\user::instance($USER->id)->id,
            'user',
            'draft',
            $draftitemid,
            'id',
            false
        );
        foreach ($files as $file) {
            if (!branding::is_valid_video($file)) {
                return get_string('categoryhero_invalidvideo', self::COMPONENT);
            }
        }
        return null;
    }

    /**
     * The pair preview: the category's own name on the solid band, in the pair it draws.
     *
     * The colours reach a `style` attribute, a CSS context Mustache's escaping does not protect, so
     * both halves pass {@see branding::hex()} here and a stored value that is not a hex colour is
     * replaced by its half's default.
     *
     * A pair of the category's own is read raw, not through {@see branding::record()} (which
     * sanitises on the way out), so the hex() calls here are the guard a test can observe. Without
     * a pair of its own the band shows the inherited pair from {@see branding::colours()}.
     *
     * @param int $categoryid The category being edited.
     * @return string The rendered block, appended raw inside the colours fieldset.
     */
    private static function pair_preview(int $categoryid): string {
        global $DB, $OUTPUT;

        $row = $DB->get_record(branding::TABLE, ['categoryid' => $categoryid], 'bgcolour, inkcolour, owncolours') ?: null;
        if ($row === null || empty($row->owncolours)) {
            $row = (object) branding::colours([$categoryid])[$categoryid];
        }
        $bgcolour = branding::hex((string) ($row?->bgcolour ?? '')) ?? branding::DEFAULT_BG;
        $inkcolour = branding::hex((string) ($row?->inkcolour ?? '')) ?? branding::DEFAULT_INK;
        $category = \core_course_category::get($categoryid, IGNORE_MISSING, true);
        return $OUTPUT->render_from_template('theme_dimensions/categoryhero_pairpreview', [
            // The plain spelling: the template double-stashes the name, which escapes it once.
            'name' => $category ? $category->get_formatted_name(['escape' => false]) : '',
            'style' => 'background-color: ' . $bgcolour . '; color: ' . $inkcolour . ';',
        ]);
    }

    /**
     * The category whose appearance this person may edit, or the theme's one refusal.
     *
     * An id that names no category and an id the person may not manage raise the same exception,
     * before the category is loaded: two answers (core's unknowncategory and nopermissions) would
     * tell a guesser which ids exist. The capability is asked at the category's own context, so
     * every manager of it passes, a hidden category included.
     *
     * @param int $categoryid The id the request names.
     * @return \core_course_category The category, hidden ones included.
     * @throws unavailable_exception When it does not exist or the person may not manage it.
     */
    public static function editable_category(int $categoryid): \core_course_category {
        $context = \core\context\coursecat::instance($categoryid, IGNORE_MISSING);
        if (!$context || !has_capability('moodle/category:manage', $context)) {
            throw new unavailable_exception();
        }
        return \core_course_category::get($categoryid, MUST_EXIST, true);
    }

    /**
     * Core's own options for the category description editor, for a category that exists.
     *
     * Mirrors {@see \core_course_editcategory_form::get_description_editor_options()}, subdirs probe
     * included; keep in step. Both editors write the same file area (coursecat / description, item 0,
     * the category's context), so a narrower set (fewer files, noclean off) would silently rewrite
     * what was saved on /course/editcategory.php.
     *
     * @param int $categoryid The category.
     * @return array The editor options.
     */
    public static function description_editor_options(int $categoryid): array {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        $context = \core\context\coursecat::instance($categoryid);
        return [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'maxbytes' => $CFG->maxbytes,
            'trusttext' => false,
            'noclean' => true,
            'context' => $context,
            'subdirs' => file_area_contains_subdirs($context, 'coursecat', 'description', 0),
        ];
    }

    /**
     * The sentence under "own colours": where the pair comes from while the category has none of its own.
     *
     * Resolved from the parent, never the category: with a pair of its own the category's resolution
     * names itself, and the sentence is about what unticking the box would give. The source's name
     * is printed only when the viewer may see that category under core's visibility
     * (core_course_category::get() without the hidden flag answers null otherwise) and under
     * local_unlistedcourses (an unlisted category is withheld): a manager of this category need not
     * manage the one above it.
     *
     * @param int $categoryid The category being edited.
     * @return string The sentence, HTML: the name in it is formatted and escaped once.
     */
    private static function inheritance_note(int $categoryid): string {
        $category = \core_course_category::get($categoryid, IGNORE_MISSING, true);
        $parentid = $category ? (int) $category->parent : 0;
        $from = $parentid > 0 ? (int) branding::colours([$parentid])[$parentid]['from'] : 0;
        if ($from === 0) {
            return get_string('categoryhero_inheritedtheme', self::COMPONENT);
        }
        $source = \core_course_category::get($from, IGNORE_MISSING);
        if ($source === null || !category_access::is_category_discoverable($from)) {
            return get_string('categoryhero_inheritedabove', self::COMPONENT);
        }
        return get_string('categoryhero_inheritedfrom', self::COMPONENT, $source->get_formatted_name());
    }

    /**
     * The default sort choices, labelled: the theme's, then the three the toolbar offers.
     *
     * Keyed by {@see criteria::SORTS} and labelled with the toolbar's own strings.
     *
     * @return string[] value => label.
     */
    public static function defaultsort_options(): array {
        return [
            '' => get_string('categoryhero_defaultsort_theme', self::COMPONENT),
            criteria::SORT_CATEGORY => get_string('category_sort_category', self::COMPONENT),
            criteria::SORT_NAME => get_string('category_sort_name', self::COMPONENT),
            criteria::SORT_RECENT => get_string('category_sort_recent', self::COMPONENT),
        ];
    }

    /**
     * The layout choices, labelled.
     *
     * @return string[] key => label.
     */
    public static function layout_options(): array {
        return [
            branding::LAYOUT_IMAGE => get_string('categoryhero_layout_image', self::COMPONENT),
            branding::LAYOUT_SOLID => get_string('categoryhero_layout_solid', self::COMPONENT),
            branding::LAYOUT_COMPACT => get_string('categoryhero_layout_compact', self::COMPONENT),
        ];
    }

    /**
     * The overlay choices, labelled.
     *
     * @return string[] percent => label.
     */
    public static function overlay_options(): array {
        return [
            0 => get_string('categoryhero_overlay_transparent', self::COMPONENT),
            60 => get_string('categoryhero_overlay_60', self::COMPONENT),
            78 => get_string('categoryhero_overlay_78', self::COMPONENT),
            90 => get_string('categoryhero_overlay_90', self::COMPONENT),
        ];
    }
}
