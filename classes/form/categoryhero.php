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
 * Never a dynamic_form: the page carries six file managers and must work with JavaScript off.
 * Four header sections - the category's own description, the appearance, the colours, the
 * public page - with short forms left on (setDisableShortforms(false)), so the accordion, its
 * expand-all control, the keyboard handling and core's own expansion of a section holding a
 * validation error come free; no JavaScript relocates any row. The colours' own section is the
 * whole two-column arrangement of the editor: the stylesheet lays that one fieldset out as a
 * grid and the contrast panel the module mounts inside it takes the right track, so the columns
 * are the form's and never a relocation. The colours are two free hex fields: with JavaScript
 * each carries a native colour swatch, and the pair gets a live preview on the band and the
 * contrast panel; with it off a manager types two hex values and still sees the stored pair.
 * What keeps an illegible hero off the site is not the widget but this form and
 * {@see branding::validate()} behind it: a value that is not a hex colour and a pair under
 * {@see branding::FLOOR} are both refused, with the measured ratio in the message. The pair is
 * the category's own only while "own colours" is ticked; unticked, the two fields are disabled,
 * show the pair the category inherits, and are neither validated nor stored.
 *
 * The description section is the one field here that is not this plugin's: it edits core's own
 * coursecat description, in core's own editor with core's own options, and the page saves it
 * through core_course_category::update(). Everything a category page shows is therefore edited
 * on one screen, and core's page keeps working on the same data.
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

        /* The category's own description, core's field in core's own editor. It is not this
           plugin's column and is never written to this plugin's table: the page saves it through
           core_course_category::update(), which is what fires course_category_updated and purges
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

        /* The card's cover, its cover video and the three crests: five single-file areas, each a
           file manager named after its file area, built from branding::single_file_areas() so
           that the element, the draft area and the stored area are one name and one option set.
           The area name is the contract - form_data() prepares and save() reads the draft under
           it, so an element renamed here would store a manager's file nowhere at all, with the
           page still saving and the form reopening empty.

           A file manager and not a filepicker: a filepicker can only replace its file and has no
           control that removes it. maxfiles is 1 in every one of the five option sets, so each
           manager still holds one file per area.

           The order is the list's: the still, the film, then the crests in badge order. The
           video's limit is the site setting's and its extension list is a name check; the bytes
           are read in validation() below. The crests' limit and vocabulary are the landing page's
           own, so a crest a manager may put on a course is not refused on the course's
           category - SVG among them (see branding::resolve()). */
        foreach (branding::single_file_areas() as $area => $options) {
            $key = self::file_area_string($area);
            $mform->addElement('filemanager', $area, get_string($key, self::COMPONENT), null, $options);
            $mform->addHelpButton($area, $key, self::COMPONENT);
        }

        /* The card's own text, beside the card's own files: what the card says in its parent's
           grid instead of the beginning of the description, which the hero keeps showing. Plain
           text capped at the length the card's excerpt is cut at, so a card with a text of its
           own is never longer than one without. */
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

        /* The organisation an address naming none gets on this category. The vocabulary is the
           address's own (criteria::SORTS), so a default is something a reader could have asked
           for, and the toolbar's chips keep every other organisation one press away. */
        $mform->addElement(
            'select',
            'defaultsort',
            get_string('categoryhero_defaultsort', self::COMPONENT),
            self::defaultsort_options()
        );
        $mform->addHelpButton('defaultsort', 'categoryhero_defaultsort', self::COMPONENT);
        $mform->setType('defaultsort', PARAM_ALPHA);
        $mform->setDefault('defaultsort', '');

        /* The colours are a section of their own, which is what lets the stylesheet put the two
           fields and the overlay in one column and the contrast panel the module mounts in the
           other: the rows stay exactly where core drew them and a CSS grid over this fieldset's
           container places them. A field belongs to the section it is declared after, so the
           order of these calls is a layout decision. The overlay's hideIf names `layout`, in the
           fieldset above: a dependency may cross a section, because M.form.initFormDependencies
           works over the whole form. */
        $mform->addElement('header', 'coloursheader', get_string('categoryhero_colours', self::COMPONENT));
        $mform->setExpanded('coloursheader', true);

        /* Whether the pair is the category's own. Unticked, the category takes the pair of its
           nearest ancestor that has one (branding::colours()), and the two fields below are
           DISABLED rather than hidden: they show the inherited pair, which form_data() put there,
           so the manager sees what the page draws and starts from it on ticking the box. The
           sentence under the box names where the pair comes from. */
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

        /* The last row of the colours section: the stored pair painted on a band with the
           category's own name, server-rendered, so it is right with JavaScript off and the module
           only has to keep it in step while the pair is typed. An `html` element and not a static
           one: QuickForm's renderer appends it raw inside this fieldset's container
           (HTML_QuickForm_html::accept() -> renderHtml()), which is the box the grid is drawn on,
           and it carries no label column of its own. */
        $mform->addElement('html', self::pair_preview($categoryid));

        $mform->addElement('header', 'publicheader', get_string('categoryhero_public', self::COMPONENT));

        /* The short public description, above the long one, because it is what a share preview
           and a search result show and the rich text is what a visitor reads on the page. The
           maxlength attribute stops the field itself at the limit, so nobody writes 400
           characters and then learns that half of them were never going to be shown; the server
           refuses past it anyway, for a browser that ignores the attribute. */
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
     * Server-side validation: every choice against its vocabulary, the tagline against its length.
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
        /* The colours are asked about only when they are the category's own: unticked, the
           fields are disabled, post nothing and store nothing, so a refusal on them would name a
           value the manager cannot even edit. */
        $owncolours = !empty($data['owncolours']);
        $bgcolour = $owncolours ? branding::hex((string) ($data['bgcolour'] ?? '')) : branding::DEFAULT_BG;
        if ($bgcolour === null) {
            $errors['bgcolour'] = get_string('categoryhero_invalidcolour', self::COMPONENT);
        }
        $inkcolour = $owncolours ? branding::hex((string) ($data['inkcolour'] ?? '')) : branding::DEFAULT_INK;
        if ($inkcolour === null) {
            $errors['inkcolour'] = get_string('categoryhero_invalidcolour', self::COMPONENT);
        }
        /* The floor is asked only of a pair that parsed: a ratio measured against a default
           standing in for a value the person has still to fix would name a number they cannot
           act on, on a field they did not type. */
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
        /* The cover video is refused here, with a message on its own field, rather than stored
           and then withheld by the route: a manager who uploads a file that is not a video would
           otherwise save a page that looks saved and get a card that never plays, with nothing
           anywhere saying why. The route and the cached payload refuse it as well, because this
           form is not the only way into the file area. */
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
        /* The public page text is link-only, and a paste or a drag-and-drop is the one way a file
           can still reach it. The address it leaves behind is refused here, on the field, rather
           than stored as a picture nobody else can see; branding::UNSERVABLE_ADDRESS says why
           each shape breaks. */
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
     * Every value here is load-bearing. On 5.2 the multimedia button and the media manager cannot
     * open on an editor without a draft area: `lib/form/editor.php` builds `$fpoptions` only when
     * `maxfiles != 0`, and `tiny_media`'s embed dialogue dereferences it unguarded
     * (`embed/embed.js`, the constructor) where its image dialogue tests
     * `typeof options !== 'undefined'` first. It is a core defect, `return_types` cannot reach
     * it, and `maxfiles` is the only lever - so the button works exactly when this editor has a
     * draft area, and the rest of this option set is what makes that draft area unusable.
     *
     * - `maxfiles => 1` builds the file-picker options, so the dialogue opens.
     * - `return_types => FILE_EXTERNAL` makes it link-only: `initialise_filepicker()` keeps only
     *   repositories that can return an external address, which leaves `areafiles`, `url` and
     *   `wikimedia` and drops `upload`. Both dialogues compute their drop zone as "some
     *   repository of type upload" (`image/image.js`, `embed/embed.js`), so with `upload` gone
     *   there is no drop zone and no upload path on either. The media manager is the one
     *   exception, and it is core's own doing: `manage.php` clears FILE_EXTERNAL out of the
     *   value it is handed ("links are allowed in textarea but never allowed in filemanager"),
     *   which for a FILE_EXTERNAL-only editor leaves 0 - and `MoodleQuickForm_filemanager`
     *   reads 0 as `empty()` and restores FILE_INTERNAL | FILE_REFERENCE | FILE_CONTROLLED_LINK
     *   in its place. So that one file manager does keep an upload repository, and says so with
     *   a drop target; the next option is what stops anything landing in it.
     * - `maxbytes => 1` and `areamaxbytes => 1` back that up: anything that did reach a
     *   repository upload - a paste of an image, a file dragged into the body, both of which go
     *   through `editor_tiny/uploader` whatever these options say, and the media manager's
     *   restored upload repository above - is refused for its size before a byte is stored.
     *   `areamaxbytes` is the half that matters, because an admin is exempt from `maxbytes`
     *   alone (`get_user_max_upload_file_size()` answers USER_CAN_IGNORE_FILE_SIZE_LIMITS for
     *   anybody holding `ignorefilesizelimits`). The paste fails earlier still, because that
     *   uploader looks for a repository of type `upload` to name in its POST and finds none.
     *
     * This text has no file area and must not get one. The draft area the element mints is
     * never handed to `file_save_draft_area_files()` by anything - {@see branding::save()} writes
     * the row's text and the branding's own areas, and none of them is this one - so a file
     * that somehow reached it would be an orphaned draft that core's own cleanup deletes.
     * {@see branding::holds_unservable_address()} is the other half, and it refuses rather than
     * strips: an address that cannot survive the trip to a public page (a draft file, a `blob:`
     * or a `data:` URI) makes {@see branding::validate()} throw and {@see validation()} report
     * `categoryhero_publictext_nofiles` on this field.
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
     * A literal per area rather than a name composed from the badge's position, so every key can
     * be found by searching for it; this is also the only place that says which row of the form
     * each of {@see branding::single_file_areas()} is. An area with no entry here is a
     * programming error and says so, rather than rendering a row labelled with a missing-string
     * notice - which on 5.2 under developer debugging is an uncaught error page, not a warning.
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
     * A file manager never posts a file. It posts the id of a draft area holding one
     * (lib/form/filemanager.php), so $files is empty here and the bytes have to be read out of
     * that area - which is also the only place they exist before the form is saved. A draft id
     * of zero is "no element was submitted", which is not a refusal; an empty draft area is the
     * manager having removed the film, which is not a refusal either, and the loop below simply
     * has nothing to grade.
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
     * The colours reach a `style` attribute, and Mustache's escaping does not protect a CSS
     * context (the hero's inline style is this feature's other such sink), so both halves pass
     * {@see branding::hex()} here and a stored value that is not a hex colour is replaced by the
     * default for its half - which is also what the field above it shows, so the band and the
     * fields agree.
     *
     * A pair of the category's own is read raw, deliberately not through
     * {@see branding::record()}, which sanitises on the way out: with the value arriving clean, the
     * hex() calls here could be deleted without any test noticing, and they are the guard between
     * a stored string and an inline style. Without a pair of its own the band shows the inherited
     * pair, from {@see branding::colours()} - the pair the fields show and the page draws.
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
            // The PLAIN spelling: the template double-stashes the name, which escapes it once.
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
     * A copy of core_course_editcategory_form::get_description_editor_options() (5.2), including
     * the subdirs probe, because the two editors write into the same file area - coursecat /
     * description, item 0, the category's context - and core's own page keeps editing it. A
     * narrower option set here would drop files core's page accepted: maxfiles unlimited and
     * noclean are core's, and lowering either would silently rewrite what a colleague saved on
     * /course/editcategory.php.
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
     * Asked about the PARENT, never the category: with a pair of its own the category's resolution
     * names itself, and the sentence is about what unticking the box would give. The source's name
     * is printed only when the viewer may see that category on both counts: core's visibility
     * (core_course_category::get() without the hidden flag answers null otherwise) and
     * local_unlistedcourses' state (an unlisted category is withheld whatever core says). A manager
     * of this category is not necessarily one of the category above it, and a withheld name is not
     * this form's to reveal.
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
     * The default organisation choices, labelled: the theme's, then the three the toolbar offers.
     *
     * Keyed by {@see criteria::SORTS}, the address's own vocabulary, and labelled with the
     * toolbar's own strings, so the editor and the chips name each organisation alike.
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
