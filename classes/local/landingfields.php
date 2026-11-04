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
 * Theme Dimensions - Landing page course custom fields
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use core_course\customfield\course_handler;
use core_customfield\api;
use core_customfield\category_controller;
use core_customfield\data_controller;
use core_customfield\field_controller;

/**
 * Provisions the course custom fields the enrolment landing page reads.
 *
 * The theme owns the display, not the data: the "Course landing page" category and its
 * fields are core course custom fields (component core_course, area course;
 * the four badge fields and the share picture are of the picture type
 * customfield_picture provides, an optional plugin: without it those five are
 * not provisioned and nothing draws them, {@see picture_type_available()}),
 * created once so a fresh site is usable without an admin building them by
 * hand, and never deleted - they hold what course authors wrote about their
 * courses. The one exception is the retired "public enrolment page" checkbox,
 * which held no authored text and whose meaning moved into
 * local_unlistedcourses' discoverability state ({@see retire_public_field()}).
 * Provisioning is idempotent and serialised under a core Lock API
 * lock, because neither customfield_field nor customfield_category carries a
 * unique index and two concurrent first requests would silently create
 * duplicate definitions.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class landingfields {
    /** @var string Component name, used for config reads and the lock namespace. */
    public const COMPONENT = 'theme_dimensions';

    /** @var string Plugin config key remembering the provisioned category id. */
    public const CATEGORYID_CONFIG = 'landingcfcategoryid';

    /** @var string Shortname of the page model select (course/event). */
    public const SHORTNAME_MODEL = 'dimensions_model';

    /** @var string Shortname of the modality select (online/in-person/hybrid). */
    public const SHORTNAME_MODALITY = 'dimensions_modality';

    /** @var string Shortname of the workload number field. */
    public const SHORTNAME_WORKLOAD = 'dimensions_workload';

    /** @var string Shortname of the target audience textarea. */
    public const SHORTNAME_AUDIENCE = 'dimensions_audience';

    /** @var string Shortname of the format select (self-paced/tutored), courses only. */
    public const SHORTNAME_FORMAT = 'dimensions_format';

    /** @var string Shortname of the live classes checkbox, tutored courses only. */
    public const SHORTNAME_LIVECLASSES = 'dimensions_liveclasses';

    /** @var string Shortname of the text saying when the live classes happen. */
    public const SHORTNAME_LIVECLASSESWHEN = 'dimensions_liveclasseswhen';

    /** @var string Shortname of the in-person workload number field, hybrid offerings only. */
    public const SHORTNAME_INPERSONWORKLOAD = 'dimensions_inpersonworkload';

    /** @var string Shortname of the in-person meetings text field, hybrid offerings only. */
    public const SHORTNAME_MEETINGS = 'dimensions_meetings';

    /**
     * @var string[] The format, live class and in-person fields, in the order they sit right after the workload.
     *
     * A site that had the other fields before them gets them appended at the end of the category
     * by {@see self::ensure_fields()}; the upgrade then moves them here with
     * {@see self::place_format_fields()}, so that an upgraded site lists the fields in the same
     * order as a fresh one.
     */
    public const ROUND6_SHORTNAMES = [
        self::SHORTNAME_FORMAT,
        self::SHORTNAME_LIVECLASSES,
        self::SHORTNAME_LIVECLASSESWHEN,
        self::SHORTNAME_INPERSONWORKLOAD,
        self::SHORTNAME_MEETINGS,
    ];

    /** @var string Shortname of the programme textarea. */
    public const SHORTNAME_PROGRAMME = 'dimensions_programme';

    /** @var string Shortname of the certification textarea. */
    public const SHORTNAME_CERTIFICATION = 'dimensions_certification';

    /** @var string Shortname of the city text field (free text). */
    public const SHORTNAME_CITY = 'dimensions_city';

    /** @var string Shortname of the venue name text field. */
    public const SHORTNAME_VENUE = 'dimensions_venue';

    /** @var string Shortname of the venue address text field. */
    public const SHORTNAME_ADDRESS = 'dimensions_address';

    /** @var string Shortname of the public description text field (never displayed; read for the Open Graph tags). */
    public const SHORTNAME_PUBLICDESCRIPTION = 'dimensions_publicdescription';

    /** @var string Shortname of the video URL text field (YouTube or Vimeo, embedded by the page). */
    public const SHORTNAME_VIDEOURL = 'dimensions_videourl';

    /** @var string Shortname of the first badge picture field. */
    public const SHORTNAME_CREST1 = 'dimensions_crest_1';

    /** @var string Shortname of the second badge picture field. */
    public const SHORTNAME_CREST2 = 'dimensions_crest_2';

    /** @var string Shortname of the third badge picture field. */
    public const SHORTNAME_CREST3 = 'dimensions_crest_3';

    /** @var string Shortname of the fourth badge picture field. */
    public const SHORTNAME_CREST4 = 'dimensions_crest_4';

    /** @var string[] The badge fields in display order: the hero's grid reads left to right, top to bottom. */
    public const CREST_SHORTNAMES = [
        self::SHORTNAME_CREST1,
        self::SHORTNAME_CREST2,
        self::SHORTNAME_CREST3,
        self::SHORTNAME_CREST4,
    ];

    /** @var int Largest badge file accepted, in bytes: a 784px PNG with alpha is about 300 KB, an SVG far less. */
    public const CREST_MAXBYTES = 2097152;

    /** @var string The custom field type of the badge fields, provided by customfield_picture. */
    public const PICTURE_TYPE = 'picture';

    /** @var string Shortname of the share picture field (never displayed; the source of the landing page's og:image). */
    public const SHORTNAME_SHAREIMAGE = 'dimensions_shareimage';

    /**
     * @var int Largest share picture accepted, in bytes.
     *
     * The picture is a SOURCE, never served as it is: the Open Graph route serves a 1200x630
     * derivative of it, and {@see landing\publicfiles::MAX_SOURCE_PIXELS} is what bounds the work of
     * making one. So the cap only has to admit a photo straight from a camera, which the badges'
     * 2 MB does not.
     */
    public const SHAREIMAGE_MAXBYTES = 8388608;

    /**
     * @var string[] Every picture field of the theme, the badges and the share picture.
     *
     * All of them are visible to nobody, so core's handler backs none of their files up: the
     * theme's backup and restore plugin classes carry the rows and the files of exactly this list.
     */
    public const PICTURE_SHORTNAMES = [
        self::SHORTNAME_CREST1,
        self::SHORTNAME_CREST2,
        self::SHORTNAME_CREST3,
        self::SHORTNAME_CREST4,
        self::SHORTNAME_SHAREIMAGE,
    ];

    /** @var string Shortname of the retired "public landing page" checkbox, kept only so the upgrade can find it. */
    public const SHORTNAME_RETIRED_PUBLIC = 'dimensions_public';

    /** @var int Stored value of the page model select for "course". */
    public const MODEL_COURSE = 1;

    /** @var int Stored value of the page model select for "event". */
    public const MODEL_EVENT = 2;

    /**
     * @var int Stored value of the page model select for "trail", the line appended for the trail model.
     *
     * A trail is a course whose steps are its mod_subcourse activities ({@see trail\trail}); the line is
     * offered only where that plugin is installed and enabled ({@see trail\trail::hides_option()}).
     */
    public const MODEL_TRAIL = 3;

    /** @var int Stored value of the modality select for "online". */
    public const MODALITY_ONLINE = 1;

    /** @var int Stored value of the modality select for "in-person". */
    public const MODALITY_INPERSON = 2;

    /** @var int Stored value of the modality select for "hybrid", the line appended after the other two. */
    public const MODALITY_HYBRID = 3;

    /** @var int Stored value of the format select for "self-paced". */
    public const FORMAT_SELFPACED = 1;

    /** @var int Stored value of the format select for "tutored". */
    public const FORMAT_TUTORED = 2;

    /**
     * @var string[] The custom field type of every field of {@see definitions()}, in the same order: shortname => type.
     *
     * The readers that only need to know whether a shortname is the theme's, and of which type
     * ({@see get_field()}, {@see load_field_cache()}, {@see values_many()}), read this list instead of
     * building the definitions, which format some forty language strings on every call.
     * landingfields_test holds the two in step, order included.
     */
    public const FIELD_TYPES = [
        self::SHORTNAME_MODEL => 'select',
        self::SHORTNAME_MODALITY => 'select',
        self::SHORTNAME_WORKLOAD => 'number',
        self::SHORTNAME_FORMAT => 'select',
        self::SHORTNAME_LIVECLASSES => 'checkbox',
        self::SHORTNAME_LIVECLASSESWHEN => 'text',
        self::SHORTNAME_INPERSONWORKLOAD => 'number',
        self::SHORTNAME_MEETINGS => 'text',
        self::SHORTNAME_AUDIENCE => 'textarea',
        self::SHORTNAME_PROGRAMME => 'textarea',
        self::SHORTNAME_CERTIFICATION => 'textarea',
        self::SHORTNAME_CITY => 'text',
        self::SHORTNAME_VENUE => 'text',
        self::SHORTNAME_ADDRESS => 'text',
        self::SHORTNAME_PUBLICDESCRIPTION => 'text',
        self::SHORTNAME_CREST1 => self::PICTURE_TYPE,
        self::SHORTNAME_CREST2 => self::PICTURE_TYPE,
        self::SHORTNAME_CREST3 => self::PICTURE_TYPE,
        self::SHORTNAME_CREST4 => self::PICTURE_TYPE,
        self::SHORTNAME_VIDEOURL => 'text',
        self::SHORTNAME_SHAREIMAGE => self::PICTURE_TYPE,
    ];

    /** @var array|null Request cache of resolved field controllers keyed by shortname (null = missing). */
    private static ?array $fieldcache = null;

    /** @var array Request cache of {@see option_lines()}: shortname => the lines, or null when the field does not resolve. */
    private static array $optionlines = [];

    /**
     * The full definition of every field this theme provisions.
     *
     * Single source of truth for provisioning and for the tests. Each entry
     * carries the customfield type, the lang string key of its name (the
     * description uses the same key with a '_desc' suffix) and the
     * type-specific configdata.
     *
     * Select values are stored as a 1-based index into the options list
     * (core prepends an empty choice at index 0), so the MODEL_*,
     * MODALITY_* and FORMAT_* constants are bound to the ORDER of the lines
     * in the 'options' strings below. Never reorder them; a new option goes at
     * the end, and a site provisioned before it needs an upgrade step that
     * appends it, as {@see add_hybrid_option()} does for the hybrid modality.
     *
     * @return array Map of shortname => ['type' => string, 'stringkey' => string, 'config' => array].
     */
    public static function definitions(): array {
        return [
            self::SHORTNAME_MODEL => [
                'type' => 'select',
                'stringkey' => 'landingfield_model',
                'config' => [
                    'options' => get_string('landingfield_model_course', self::COMPONENT) . "\n" .
                        get_string('landingfield_model_event', self::COMPONENT) . "\n" .
                        get_string('landingfield_model_trail', self::COMPONENT),
                    'defaultvalue' => '',
                ],
            ],
            self::SHORTNAME_MODALITY => [
                'type' => 'select',
                'stringkey' => 'landingfield_modality',
                'config' => [
                    'options' => get_string('landingfield_modality_online', self::COMPONENT) . "\n" .
                        get_string('landingfield_modality_inperson', self::COMPONENT) . "\n" .
                        get_string('landingfield_modality_hybrid', self::COMPONENT),
                    'defaultvalue' => '',
                ],
            ],
            self::SHORTNAME_WORKLOAD => [
                'type' => 'number',
                'stringkey' => 'landingfield_workload',
                'config' => [
                    'decimalplaces' => 0,
                    'minimumvalue' => 0,
                    'maximumvalue' => '',
                    'display' => '{value}',
                    'displaywhenzero' => '0',
                    'fieldtype' => '',
                ],
            ],
            self::SHORTNAME_FORMAT => [
                'type' => 'select',
                'stringkey' => 'landingfield_format',
                'config' => [
                    'options' => get_string('landingfield_format_selfpaced', self::COMPONENT) . "\n" .
                        get_string('landingfield_format_tutored', self::COMPONENT),
                    'defaultvalue' => '',
                ],
            ],
            self::SHORTNAME_LIVECLASSES => [
                'type' => 'checkbox',
                'stringkey' => 'landingfield_liveclasses',
                'config' => [
                    'checkbydefault' => 0,
                ],
            ],
            self::SHORTNAME_LIVECLASSESWHEN => [
                'type' => 'text',
                'stringkey' => 'landingfield_liveclasseswhen',
                'config' => [
                    'displaysize' => 50,
                    'maxlength' => 120,
                    'ispassword' => 0,
                    'link' => '',
                    'linktarget' => '',
                ],
            ],
            self::SHORTNAME_INPERSONWORKLOAD => [
                'type' => 'number',
                'stringkey' => 'landingfield_inpersonworkload',
                'config' => [
                    'decimalplaces' => 0,
                    'minimumvalue' => 0,
                    'maximumvalue' => '',
                    'display' => '{value}',
                    'displaywhenzero' => '0',
                    'fieldtype' => '',
                ],
            ],
            self::SHORTNAME_MEETINGS => [
                'type' => 'text',
                'stringkey' => 'landingfield_meetings',
                'config' => [
                    'displaysize' => 50,
                    'maxlength' => 120,
                    'ispassword' => 0,
                    'link' => '',
                    'linktarget' => '',
                ],
            ],
            self::SHORTNAME_AUDIENCE => [
                'type' => 'textarea',
                'stringkey' => 'landingfield_audience',
                'config' => [
                    'defaultvalue' => '',
                    'defaultvalueformat' => FORMAT_HTML,
                ],
            ],
            self::SHORTNAME_PROGRAMME => [
                'type' => 'textarea',
                'stringkey' => 'landingfield_programme',
                'config' => [
                    'defaultvalue' => '',
                    'defaultvalueformat' => FORMAT_HTML,
                ],
            ],
            self::SHORTNAME_CERTIFICATION => [
                'type' => 'textarea',
                'stringkey' => 'landingfield_certification',
                'config' => [
                    'defaultvalue' => '',
                    'defaultvalueformat' => FORMAT_HTML,
                ],
            ],
            self::SHORTNAME_CITY => [
                'type' => 'text',
                'stringkey' => 'landingfield_city',
                'config' => [
                    'displaysize' => 50,
                    'maxlength' => 120,
                    'ispassword' => 0,
                    'link' => '',
                    'linktarget' => '',
                ],
            ],
            self::SHORTNAME_VENUE => [
                'type' => 'text',
                'stringkey' => 'landingfield_venue',
                'config' => [
                    'displaysize' => 50,
                    'maxlength' => 255,
                    'ispassword' => 0,
                    'link' => '',
                    'linktarget' => '',
                ],
            ],
            self::SHORTNAME_ADDRESS => [
                'type' => 'text',
                'stringkey' => 'landingfield_address',
                'config' => [
                    'displaysize' => 50,
                    'maxlength' => 255,
                    'ispassword' => 0,
                    'link' => '',
                    'linktarget' => '',
                ],
            ],
            self::SHORTNAME_PUBLICDESCRIPTION => [
                'type' => 'text',
                'stringkey' => 'landingfield_publicdescription',
                'config' => [
                    'displaysize' => 50,
                    /* Open Graph descriptions are cut at about 200 characters by every
                       consumer that matters; the limit is enforced where it is typed. */
                    'maxlength' => 200,
                    'ispassword' => 0,
                    'link' => '',
                    'linktarget' => '',
                    /* Never displayed on a course card - it exists for the og:description of
                       the public landing page, and it is the reviewed text for an outside audience,
                       never the course summary. */
                    'visibility' => course_handler::NOTVISIBLE,
                ],
            ],
        ] + self::badge_definitions() + [
            self::SHORTNAME_VIDEOURL => [
                'type' => 'text',
                'stringkey' => 'landingfield_videourl',
                'config' => [
                    'displaysize' => 50,
                    'maxlength' => 255,
                    'ispassword' => 0,
                    'link' => '',
                    'linktarget' => '',
                    // The landing page embeds it; a bare address on a course card would be noise.
                    'visibility' => course_handler::NOTVISIBLE,
                ],
            ],
            // Last, so that a fresh site and an upgraded one list the fields in the same order.
            self::SHORTNAME_SHAREIMAGE => [
                'type' => self::PICTURE_TYPE,
                'stringkey' => 'landingfield_shareimage',
                'config' => [
                    'maximumbytes' => self::SHAREIMAGE_MAXBYTES,
                    /* Never displayed on the platform - it exists for the og:image of the public
                       landing page, the pair of the public description above. */
                    'visibility' => course_handler::NOTVISIBLE,
                ],
            ],
        ];
    }

    /**
     * The definitions of the four badge picture fields, one per grid position.
     *
     * Visible to nobody on purpose: the landing page is their only display, and a
     * course card printing "Badge 1: <image>" for editing teachers is not one.
     * The price of that choice is paid by the theme's own backup plugin class:
     * core annotates a picture field's file for backup only through the
     * controllers of fields the backup user can view, so with every badge field
     * hidden, backup_theme_dimensions_plugin annotates the area itself.
     *
     * @return array Map of shortname => definition, in display order.
     */
    private static function badge_definitions(): array {
        $definitions = [];
        foreach (self::CREST_SHORTNAMES as $position => $shortname) {
            $definitions[$shortname] = [
                'type' => self::PICTURE_TYPE,
                'stringkey' => 'landingfield_crest' . ($position + 1),
                'config' => [
                    'maximumbytes' => self::CREST_MAXBYTES,
                    'visibility' => course_handler::NOTVISIBLE,
                ],
            ];
        }
        return $definitions;
    }

    /**
     * Whether the custom field type a definition needs is usable here.
     *
     * Only the picture fields need a plugin of their own, and it is optional: {@see picture_type_available()}.
     *
     * @param array $definition One entry of {@see definitions()}.
     * @return bool Whether the type can be created.
     */
    private static function type_available(array $definition): bool {
        if ($definition['type'] !== self::PICTURE_TYPE) {
            return true;
        }
        return self::picture_type_available();
    }

    /**
     * Whether customfield_picture is installed and enabled, so the five picture fields can exist.
     *
     * The plugin is recommended, not required. Without it the badges and the share picture are not
     * provisioned, and every reader of them ({@see pictures()}, {@see typed_values()}) answers
     * "none" through its instanceof check, so the landing page and the category listing draw without
     * them. A DISABLED type counts as absent too: core hides its fields from the course field list,
     * so they would read as missing, and provisioning them again would only collide with the hidden
     * ones on their shortnames.
     *
     * Read from the file system and the type's own config, never through the plugin manager, whose
     * list of installed plugins may have been cached earlier in the very install run that calls
     * {@see ensure_fields()}.
     *
     * @return bool
     */
    public static function picture_type_available(): bool {
        return \core_component::get_plugin_directory('customfield', self::PICTURE_TYPE) !== null
            && empty(get_config('customfield_' . self::PICTURE_TYPE, 'disabled'));
    }

    /**
     * Get one of the theme's fields, or null when missing (or created with the wrong type).
     *
     * A field whose shortname matches but whose type differs (an admin created
     * it manually before this theme was installed) is treated as unavailable
     * rather than adopted; load_field_cache() reports it through debugging().
     *
     * @param string $shortname One of the SHORTNAME_* constants.
     * @return field_controller|null The field controller.
     * @throws \coding_exception When the shortname is not one of the theme's fields.
     */
    public static function get_field(string $shortname): ?field_controller {
        if (!isset(self::FIELD_TYPES[$shortname])) {
            throw new \coding_exception("Unknown landing page field shortname '{$shortname}'.");
        }
        if (self::$fieldcache === null) {
            self::load_field_cache();
        }
        return self::$fieldcache[$shortname];
    }

    /**
     * Reset the request-level field cache (used by tests and after provisioning).
     *
     * The memos built over the same fields go with it: the option lines of the selects, and the
     * filter fields of the showcase ({@see category\facets::fields()}), so a test or a step that
     * edits a field and resets here reads the field as it is now everywhere.
     *
     * @return void
     */
    public static function reset_field_cache(): void {
        self::$fieldcache = null;
        self::$optionlines = [];
        category\facets::reset_fields();
    }

    /**
     * Ensure the landing page course custom fields exist, creating the missing ones.
     *
     * Invoked from db/install.php, and by any upgrade step that
     * introduces fields. Guarded on the customfield tables existing, not
     * on during_initial_install() - that stays true for the whole of a fresh
     * site install, which is exactly when db/install.php runs. The picture
     * fields are skipped while their type is not available, so a site without
     * customfield_picture takes no lock and writes no debugging line on every
     * call; installing the plugin later and running this again creates them.
     *
     * @return void
     */
    public static function ensure_fields(): void {
        global $DB;

        if (!$DB->get_manager()->table_exists('customfield_field')) {
            return;
        }

        // Fast path: everything resolves (request-cached).
        if (!self::missing_definitions()) {
            return;
        }

        /* Provisioning is check-then-act and the customfield tables have no
           unique indexes, so concurrent first requests must be serialised. */
        $lockfactory = \core\lock\lock_config::get_lock_factory(self::COMPONENT);
        $lock = $lockfactory->get_lock('landingfields', 10);
        if (!$lock) {
            // Another request is provisioning right now; nothing to do here.
            return;
        }
        try {
            // Re-read fresh: the lock winner may have created the fields already.
            self::reset_field_cache();
            foreach (self::missing_definitions() as $shortname => $definition) {
                self::create_field($shortname, $definition);
            }
        } finally {
            self::reset_field_cache();
            $lock->release();
        }
    }

    /**
     * The definitions whose field does not currently resolve, among those whose type is available.
     *
     * Public because the recommendation item asks the same question the provisioner does.
     *
     * @return array Subset of {@see definitions()}, keyed by shortname.
     */
    public static function missing_definitions(): array {
        $missing = [];
        foreach (self::definitions() as $shortname => $definition) {
            if (!self::type_available($definition)) {
                continue;
            }
            if (!self::get_field($shortname)) {
                $missing[$shortname] = $definition;
            }
        }
        return $missing;
    }

    /**
     * Resolve every provisioned field in one pass over the course field area.
     *
     * Reads through {@see api::get_categories_with_fields()} directly instead
     * of the course handler instance: the handler memoises its category list
     * per request, which would hide fields another request provisioned while
     * we waited on the lock. The api call is plain SQL every time, and scoped
     * to component core_course it never returns shared categories.
     *
     * @return void
     */
    private static function load_field_cache(): void {
        self::$fieldcache = array_fill_keys(array_keys(self::FIELD_TYPES), null);

        $categories = api::get_categories_with_fields('core_course', 'course', 0);
        foreach ($categories as $category) {
            foreach ($category->get_fields() as $field) {
                $shortname = $field->get('shortname');
                if (!array_key_exists($shortname, self::$fieldcache)) {
                    continue;
                }
                $expected = self::FIELD_TYPES[$shortname];
                if ($field->get('type') !== $expected) {
                    debugging(
                        self::COMPONENT . ": field '{$shortname}' exists with unexpected type '" .
                            $field->get('type') . "', expected '{$expected}' - not used.",
                        DEBUG_DEVELOPER
                    );
                    continue;
                }
                self::$fieldcache[$shortname] = $field;
            }
        }
    }

    /**
     * Create one field inside the theme's category, creating the category first if needed.
     *
     * The display name and description are resolved through get_string() at
     * creation time and stored once - custom field names are not translatable
     * after creation, exactly like the focus mode field {@see focusmodefields}.
     *
     * @param string $shortname The field shortname.
     * @param array $definition The entry from {@see definitions()}.
     * @return void
     */
    private static function create_field(string $shortname, array $definition): void {
        $handler = course_handler::create();

        if (!api::is_shortname_unique($handler, $shortname, 0)) {
            /* A field with this shortname exists somewhere the handler can see
               (possibly a shared field, possibly the wrong type). Creating
               another one would collide in the course edit form element names. */
            debugging(self::COMPONENT . ": shortname '{$shortname}' already taken - field not created.", DEBUG_DEVELOPER);
            return;
        }

        $category = self::get_or_create_category();
        if (!$category) {
            return;
        }

        $config = $definition['config'] + [
            'defaultvalue' => '',
            'defaultvalueformat' => FORMAT_PLAIN,
            'required' => 0,
            'uniquevalues' => 0,
            'locked' => 0,
            'visibility' => course_handler::VISIBLETOALL,
        ];

        $record = (object) [
            'type' => $definition['type'],
            'shortname' => $shortname,
            'name' => get_string($definition['stringkey'], self::COMPONENT),
            'description' => get_string($definition['stringkey'] . '_desc', self::COMPONENT),
            'descriptionformat' => FORMAT_HTML,
            'configdata' => json_encode($config),
        ];

        $field = field_controller::create(0, (object) ['type' => $definition['type']], $category);
        $handler->save_field_configuration($field, $record);
    }

    /**
     * Read every landing page field value of one course, typed for the page.
     *
     * Reads the UNFILTERED instance data (returnall) because the caller is
     * server-side display logic that decides for itself - notably the public
     * description, whose field visibility is "nobody". Each value
     * is read the way its sink needs it: selects as raw stored values, the
     * number raw through get_value() (export_value() would return the
     * displaywhenzero text), text fields as the plain spelling (consumers
     * escape for themselves), and textareas through export_value(), which
     * returns final HTML for a triple-stash sink.
     *
     * A field whose shortname matches ours but whose type differs (a foreign
     * field that pre-dates the theme) is ignored, mirroring get_field().
     *
     * The badges arrive as the stored files themselves, in grid order and without
     * gaps (a field left empty is skipped, the next one moves up), because the
     * page serves them through its own file route: the picture type's own URL
     * goes through the field's visibility rule, which refuses a hidden field to
     * everyone. The video URL is the plain spelling of what the editor typed;
     * the page decides whether a player embeds it.
     *
     * @param int $courseid The course id.
     * The format, live class and in-person fields arrive already read without contradiction
     * ({@see coherent()}): a page never has to ask whether a format, a live class or an
     * in-person figure may be shown beside the model and the modality it was saved with.
     *
     * @return \stdClass Typed values: model/modality/format (int, 0 = unset), workload/
     *   inpersonworkload (float|null), liveclasses (bool), audience/programme/certification (HTML
     *   string|null), city/venue/address/publicdescription/videourl/liveclasseswhen/meetings
     *   (plain string|null), crests (array of \stored_file in grid order), names (array of
     *   shortname => plain field name).
     */
    public static function values(int $courseid): \stdClass {
        return self::values_many([$courseid], true)[$courseid];
    }

    /**
     * Read every landing page field value of many courses, typed for the page, in one statement.
     *
     * The bulk twin of {@see values()}, and the only reader a listing may use:
     * course_handler::get_instance_data() costs a statement per course, and
     * core's own bulk method opens a recordset - three reads on PostgreSQL
     * against one on MariaDB - so the rows are read here with one bounded
     * array statement (our field ids, the page's course ids; rides the
     * instanceid-fieldid-component-area-itemid index) and turned into the
     * same data controllers core would build, defaults included for a course
     * without a row. Everything after that is exactly what values() did.
     *
     * The course contexts are asked for one by one; a caller listing a page
     * preloads them first (population::page_contexts(), which cards::values()
     * does) so no statement is paid for them here.
     *
     * A listing must not format the textareas the way the landing page does. Core's
     * textarea export runs format_text() with the text filters on, in the
     * course's context, and the first format_text() in a context loads that
     * context's active filters (a recordset) and then lets every active filter
     * run - filter_activitynames alone reads the course record for each context
     * it meets. On a page of 48 cards that is dozens of statements the card's
     * excerpt cannot justify, so a listing asks for the textareas with $filters
     * false: the same cleaning, the same pluginfile rewriting, no filter pass.
     * The landing page keeps the full, filtered rendering. The rewrite mirrors
     * customfield_textarea's data_controller::export_value() and must be
     * re-read against it on each core upgrade.
     *
     * Three things are read once for the call and never per course, and each is exactly what the
     * per-course reading answered (landingfields_test pins every one against values() course by course):
     *
     * - the fields' own facts - shortname, type, name and, while the site does not run the text filters
     *   over strings (`filterall` off), the formatted name, which format_string() then derives from the
     *   string alone, whatever the context;
     * - one DEFAULT controller per field, shared by every course without a row: a default reads only the
     *   field (select, number, text and checkbox answer get_default_value(), a textarea formats its
     *   default in the field's configuration context, a picture has no data id and so no file), never
     *   the course it was created for, which is what makes sharing it safe. A controller over a stored
     *   row is never shared;
     * - the badge files of every stored badge row, in ONE {files} statement ({@see crest_files()}), where
     *   customfield_picture's get_file() costs one statement per badge.
     *
     * A caller that reads only some of the values names their fields in $only (a listing's card:
     * {@see category\cards::CARD_SHORTNAMES}): the other fields are neither read nor turned into
     * controllers, and the keys they fill keep their empty values. Without $only every field is read,
     * as values() needs.
     *
     * @param int[] $courseids The courses.
     * @param bool $filters Whether the textareas go through the text filters (the landing page) or not (a listing).
     * @param string[]|null $only The shortnames to read, null for every field; `names` then holds these alone.
     * @return \stdClass[] courseid => the typed values of values(), for every id given.
     */
    public static function values_many(array $courseids, bool $filters = true, ?array $only = null): array {
        global $DB;

        $courseids = array_values(array_unique(array_map('intval', $courseids)));
        $results = [];
        if (!$courseids) {
            return $results;
        }

        $fields = [];
        $table = [];
        foreach (course_handler::create()->get_fields() as $field) {
            $shortname = $field->get('shortname');
            if ($field->get('type') !== (self::FIELD_TYPES[$shortname] ?? null)) {
                continue;
            }
            if ($only !== null && !in_array($shortname, $only, true)) {
                continue;
            }
            $fieldid = (int) $field->get('id');
            $fields[$fieldid] = $field;
            $table[$fieldid] = ['shortname' => $shortname, 'name' => (string) $field->get('name')];
        }

        $data = array_fill_keys($courseids, []);
        $badgedata = [];
        if ($fields) {
            [$fsql, $fparams] = $DB->get_in_or_equal(array_keys($fields), SQL_PARAMS_NAMED, 'fld');
            [$csql, $cparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'crs');
            $rows = $DB->get_records_sql(
                "SELECT d.*
                   FROM {customfield_data} d
                  WHERE d.instanceid {$csql} AND d.fieldid {$fsql}
                        AND d.component = :component AND d.area = :area AND d.itemid = 0",
                $cparams + $fparams + ['component' => 'core_course', 'area' => 'course']
            );
            foreach ($rows as $row) {
                $fieldid = (int) $row->fieldid;
                $controller = data_controller::create(0, $row, $fields[$fieldid]);
                $data[(int) $row->instanceid][$fieldid] = $controller;
                if (
                    in_array($table[$fieldid]['shortname'], self::CREST_SHORTNAMES, true)
                    && $controller instanceof \customfield_picture\data_controller
                ) {
                    $badgedata[(int) $controller->get('id')] = $controller;
                }
            }
        }
        $badges = self::crest_files($badgedata);

        // Without the string filters a name formats the same in every context: once for the call.
        $names = null;
        if (!\core\di::get(\core\formatting::class)->get_filterall()) {
            $names = [];
            $context = \core\context\course::instance($courseids[0]);
            foreach ($table as $fieldid => $facts) {
                $names[$fieldid] = format_string($facts['name'], true, ['context' => $context, 'escape' => false]);
            }
        }

        $defaults = [];
        foreach ($courseids as $courseid) {
            $controllers = [];
            foreach ($fields as $fieldid => $field) {
                // Only a default is shared: it reads the field and nothing of the course it was made for.
                $controllers[$fieldid] = $data[$courseid][$fieldid]
                    ?? ($defaults[$fieldid] ??= data_controller::create(0, (object) ['instanceid' => $courseid], $field));
            }
            $results[$courseid] = self::typed_values($courseid, $controllers, $table, $names, $filters, $badges);
        }
        return $results;
    }

    /**
     * The file of every stored badge row, in one statement: what customfield_picture's get_file() answers row by row.
     *
     * get_file() reads the field's area at the row's own context with the data id as the item id,
     * directories left out, ordered by item id, path and name, and takes the first file. The same
     * statement over every row at once asks for all the rows' item ids under all their contexts, so a
     * file is kept only where its context is its own row's (two courses' rows never share an item id,
     * but nothing here relies on that), and the first in that order wins. The columns are the ones
     * file_storage reads for a stored file, the reference ones included, so get_file_instance() builds
     * the same object get_area_files() would.
     *
     * @param \customfield_picture\data_controller[] $controllers The badge rows, keyed by data id.
     * @return array data id => \stored_file or null, for every row given.
     */
    private static function crest_files(array $controllers): array {
        global $DB;

        $files = array_fill_keys(array_keys($controllers), null);
        if (!$controllers) {
            return $files;
        }
        $contexts = [];
        foreach ($controllers as $dataid => $controller) {
            $contexts[$dataid] = (int) $controller->get_context()->id;
        }
        [$isql, $iparams] = $DB->get_in_or_equal(array_keys($contexts), SQL_PARAMS_NAMED, 'bdg');
        [$csql, $cparams] = $DB->get_in_or_equal(array_values(array_unique($contexts)), SQL_PARAMS_NAMED, 'bctx');
        // Rides component-filearea-contextid-itemid of {files}; both lists are bounded by the page.
        $records = $DB->get_records_sql(
            "SELECT f.id, f.contenthash, f.pathnamehash, f.contextid, f.component, f.filearea, f.itemid, f.filepath,
                    f.filename, f.userid, f.filesize, f.mimetype, f.status, f.source, f.author, f.license,
                    f.timecreated, f.timemodified, f.sortorder, f.referencefileid,
                    r.repositoryid, r.reference, r.lastsync AS referencelastsync
               FROM {files} f
          LEFT JOIN {files_reference} r ON f.referencefileid = r.id
              WHERE f.component = :component AND f.filearea = :filearea
                    AND f.contextid {$csql} AND f.itemid {$isql} AND f.filename <> :dot
           ORDER BY f.itemid, f.filepath, f.filename",
            $cparams + $iparams + [
                'component' => 'customfield_picture',
                'filearea' => \customfield_picture\data_controller::FILEAREA,
                'dot' => '.',
            ]
        );
        $fs = get_file_storage();
        foreach ($records as $record) {
            $dataid = (int) $record->itemid;
            if ($files[$dataid] !== null || (int) $record->contextid !== $contexts[$dataid]) {
                continue;
            }
            $files[$dataid] = $fs->get_file_instance($record);
        }
        return $files;
    }

    /**
     * Turn one course's data controllers into the typed values of values().
     *
     * @param int $courseid The course id.
     * @param data_controller[] $controllers One controller per landing page field read, defaults included, keyed by field id.
     * @param array $table The fields' facts, field id => ['shortname' => string, 'name' => raw name].
     * @param string[]|null $names The formatted names, field id => plain, or null to format them in this course's context.
     * @param bool $filters Whether the textareas go through the text filters.
     * @param array $badges The badge files, data id => \stored_file or null ({@see crest_files()}).
     * @return \stdClass The typed values.
     */
    private static function typed_values(
        int $courseid,
        array $controllers,
        array $table,
        ?array $names,
        bool $filters,
        array $badges
    ): \stdClass {
        $values = (object) [
            'model' => 0,
            'modality' => 0,
            'format' => 0,
            'liveclasses' => false,
            'liveclasseswhen' => null,
            'workload' => null,
            'inpersonworkload' => null,
            'meetings' => null,
            'audience' => null,
            'programme' => null,
            'certification' => null,
            'city' => null,
            'venue' => null,
            'address' => null,
            'publicdescription' => null,
            'videourl' => null,
            'crests' => [],
            'names' => [],
        ];
        $found = [];

        $context = \core\context\course::instance($courseid);
        foreach ($controllers as $fieldid => $data) {
            $shortname = $table[$fieldid]['shortname'];
            $values->names[$shortname] = $names[$fieldid] ?? format_string($table[$fieldid]['name'], true, [
                'context' => $context,
                'escape' => false,
            ]);

            switch ($shortname) {
                case self::SHORTNAME_MODEL:
                    $values->model = (int) $data->get_value();
                    break;
                case self::SHORTNAME_MODALITY:
                    $values->modality = (int) $data->get_value();
                    break;
                case self::SHORTNAME_WORKLOAD:
                    $values->workload = self::hours_value($data->get_value());
                    break;
                case self::SHORTNAME_FORMAT:
                    $values->format = (int) $data->get_value();
                    break;
                case self::SHORTNAME_LIVECLASSES:
                    $values->liveclasses = (bool) $data->get_value();
                    break;
                case self::SHORTNAME_LIVECLASSESWHEN:
                    $values->liveclasseswhen = self::plain_text_value($data->get_value(), $context);
                    break;
                case self::SHORTNAME_INPERSONWORKLOAD:
                    $values->inpersonworkload = self::hours_value($data->get_value());
                    break;
                case self::SHORTNAME_MEETINGS:
                    $values->meetings = self::plain_text_value($data->get_value(), $context);
                    break;
                case self::SHORTNAME_AUDIENCE:
                    $values->audience = self::textarea_value($data, $context, $filters);
                    break;
                case self::SHORTNAME_PROGRAMME:
                    $values->programme = self::textarea_value($data, $context, $filters);
                    break;
                case self::SHORTNAME_CERTIFICATION:
                    $values->certification = self::textarea_value($data, $context, $filters);
                    break;
                case self::SHORTNAME_CITY:
                    $values->city = self::plain_text_value($data->get_value(), $context);
                    break;
                case self::SHORTNAME_VENUE:
                    $values->venue = self::plain_text_value($data->get_value(), $context);
                    break;
                case self::SHORTNAME_ADDRESS:
                    $values->address = self::plain_text_value($data->get_value(), $context);
                    break;
                case self::SHORTNAME_PUBLICDESCRIPTION:
                    $values->publicdescription = self::plain_text_value($data->get_value(), $context);
                    break;
                case self::SHORTNAME_VIDEOURL:
                    $values->videourl = self::plain_text_value($data->get_value(), $context);
                    break;
                case self::SHORTNAME_CREST1:
                case self::SHORTNAME_CREST2:
                case self::SHORTNAME_CREST3:
                case self::SHORTNAME_CREST4:
                    /* The customfield_picture controller knows where its file is; a foreign 'picture' type would
                       not. A default has no data id and so no file, as get_file() answers. */
                    if ($data instanceof \customfield_picture\data_controller) {
                        $found[$shortname] = $badges[(int) $data->get('id')] ?? null;
                    }
                    break;
            }
        }
        foreach (self::CREST_SHORTNAMES as $shortname) {
            if (!empty($found[$shortname])) {
                $values->crests[] = $found[$shortname];
            }
        }
        return self::coherent($values);
    }

    /**
     * Read the format, live class and in-person values without contradiction, whatever was stored.
     *
     * The course form refuses a self-paced course that is not online and hides what does not
     * apply ({@see hook\course\after_form_validation}, {@see hook\course\after_form_definition}),
     * but a hidden field keeps the value it had, and a restore or a web service writes the
     * fields without the form. So the page reads them through one rule, applied to the stored
     * values all at once:
     *
     * - the format belongs to courses: none on an event, and a self-paced one is dropped beside
     *   an in-person or hybrid modality, which it contradicts (a tutored one stays);
     * - live classes belong to tutored courses: none on an event or a self-paced course;
     * - the in-person workload and meetings belong to hybrid offerings that are not self-paced;
     * - when the live classes happen is said only where there are live classes.
     *
     * @param \stdClass $values The typed values as stored.
     * @return \stdClass The same object, with what contradicts the rest emptied.
     */
    public static function coherent(\stdClass $values): \stdClass {
        $event = $values->model === self::MODEL_EVENT;
        $selfpaced = $values->format === self::FORMAT_SELFPACED;
        $hybrid = $values->modality === self::MODALITY_HYBRID;
        $notonline = in_array($values->modality, [self::MODALITY_INPERSON, self::MODALITY_HYBRID], true);

        if ($event || $selfpaced) {
            $values->liveclasses = false;
        }
        if (!$values->liveclasses) {
            $values->liveclasseswhen = null;
        }
        if (!$hybrid || $selfpaced) {
            $values->inpersonworkload = null;
            $values->meetings = null;
        }
        if ($event || ($selfpaced && $notonline)) {
            $values->format = 0;
        }
        return $values;
    }

    /**
     * A workload number field's value: a positive number of hours, or null.
     *
     * Read raw through get_value(), because export_value() returns the displaywhenzero text.
     *
     * @param mixed $raw The stored decvalue.
     * @return float|null The hours, or null when empty or not positive.
     */
    private static function hours_value($raw): ?float {
        return (is_numeric($raw) && (float) $raw > 0) ? (float) $raw : null;
    }

    /**
     * The badge files of a course, in grid order, and nothing else.
     *
     * What the file route needs per badge request: values() also formats every
     * name and runs the textareas through format_text(), which an image request
     * has no use for. Same source, same order as values()->crests.
     *
     * @param int $courseid The course id.
     * @return \stored_file[] The badges in grid order, gaps closed.
     */
    public static function badges(int $courseid): array {
        return array_values(self::pictures($courseid, self::CREST_SHORTNAMES));
    }

    /**
     * The share picture of a course: the source of its landing page's og:image, when an editor chose one.
     *
     * Read alone, like {@see badges()}: the Open Graph route asks for it on every request for the
     * image, and a page render asks once, neither with any use for the rest of values().
     *
     * @param int $courseid The course id.
     * @return \stored_file|null The picture, or null when none was uploaded.
     */
    public static function share_picture(int $courseid): ?\stored_file {
        return self::pictures($courseid, [self::SHORTNAME_SHAREIMAGE])[self::SHORTNAME_SHAREIMAGE] ?? null;
    }

    /**
     * The stored files of some of the theme's picture fields, in the order asked, gaps closed.
     *
     * Through customfield_picture's data_controller::get_file(), because the picture type's own
     * URL applies the field's visibility rule and refuses a hidden field to everyone - and every
     * picture field of this theme is hidden. A field of another type under one of these
     * shortnames is ignored.
     *
     * @param int $courseid The course id.
     * @param string[] $shortnames The fields asked for, in the order wanted.
     * @return \stored_file[] shortname => file, for the fields that hold one.
     */
    private static function pictures(int $courseid, array $shortnames): array {
        $found = [];
        foreach (course_handler::create()->get_instance_data($courseid, true) as $data) {
            $field = $data->get_field();
            $shortname = $field->get('shortname');
            if (
                in_array($shortname, $shortnames, true)
                && $field->get('type') === self::PICTURE_TYPE
                && $data instanceof \customfield_picture\data_controller
            ) {
                $found[$shortname] = $data->get_file();
            }
        }
        $ordered = [];
        foreach ($shortnames as $shortname) {
            if (!empty($found[$shortname])) {
                $ordered[$shortname] = $found[$shortname];
            }
        }
        return $ordered;
    }

    /**
     * The page model of a course (MODEL_*), 0 when unset - read alone, without formatting anything.
     *
     * @param int $courseid The course id.
     * @return int The stored model value.
     */
    public static function model(int $courseid): int {
        foreach (course_handler::create()->get_instance_data($courseid, true) as $data) {
            $field = $data->get_field();
            if ($field->get('shortname') === self::SHORTNAME_MODEL && $field->get('type') === 'select') {
                return (int) $data->get_value();
            }
        }
        return 0;
    }

    /**
     * The stored text of one select option, as the field is actually configured.
     *
     * Select values are stored as a 1-based index into the options list, so
     * this is the only correct way to turn a stored value back into its label
     * (the admin may have edited the option texts since provisioning).
     *
     * @param string $shortname One of the select SHORTNAME_* constants.
     * @param int $value The stored 1-based option value.
     * @return string|null The option text, or null when unset/out of range.
     */
    public static function option_label(string $shortname, int $value): ?string {
        if ($value < 1) {
            return null;
        }
        $options = self::option_lines($shortname);
        return $options[$value - 1] ?? null;
    }

    /**
     * The option lines of one select field, as the field is actually configured.
     *
     * The one split of a select's options in this theme: {@see option_label()} reads a
     * stored value through it and {@see landingdefaults} counts and offers the options
     * through it, so a default can never point at a line the label would not find.
     *
     * @param string $shortname One of the select SHORTNAME_* constants.
     * @return array|null The lines, 0-based (index + 1 is the stored value), or null when the field does not resolve.
     */
    public static function option_lines(string $shortname): ?array {
        // Memoised per request beside the field cache, and reset with it ({@see reset_field_cache()}).
        if (array_key_exists($shortname, self::$optionlines)) {
            return self::$optionlines[$shortname];
        }
        $field = self::get_field($shortname);
        if (!$field || $field->get('type') !== 'select') {
            return self::$optionlines[$shortname] = null;
        }
        return self::$optionlines[$shortname] = preg_split(
            "/\s*\n\s*/",
            trim((string) $field->get_configdata_property('options'))
        );
    }

    /**
     * The HTML of a textarea field, with or without the text filters.
     *
     * With $filters true this is exactly core's export_value(); without, the
     * same pluginfile rewriting and cleaning with the filter pass skipped -
     * see values_many() for why a listing asks for that.
     *
     * @param data_controller $data The textarea's controller, a default included.
     * @param \core\context\course $context The course context.
     * @param bool $filters Whether the text filters run.
     * @return string|null Final HTML, or null when empty.
     */
    private static function textarea_value(data_controller $data, \core\context\course $context, bool $filters): ?string {
        global $CFG;

        if ($filters) {
            return $data->export_value();
        }
        require_once($CFG->libdir . '/filelib.php');
        $value = $data->get_value();
        // The textarea controller's own emptiness rule (its is_empty() is protected).
        if (html_is_blank($value)) {
            return null;
        }
        if ($dataid = $data->get('id')) {
            $processed = file_rewrite_pluginfile_urls(
                $value,
                'pluginfile.php',
                $context->id,
                'customfield_textarea',
                'value',
                $dataid
            );
            return format_text($processed, $data->get('valueformat'), [
                'context' => $context,
                'trusted' => $data->get('valuetrust'),
                'filter' => false,
            ]);
        }
        $field = $data->get_field();
        $configcontext = $field->get_handler()->get_configuration_context();
        $processed = file_rewrite_pluginfile_urls(
            $value,
            'pluginfile.php',
            $configcontext->id,
            'customfield_textarea',
            'defaultvalue',
            $field->get('id')
        );
        return format_text($processed, $field->get_configdata_property('defaultvalueformat'), [
            'context' => $configcontext,
            'trusted' => true,
            'filter' => false,
        ]);
    }

    /**
     * Normalise a raw text-field value into the plain (unescaped) spelling.
     *
     * @param mixed $raw The raw charvalue.
     * @param \core\context\course $context The course context.
     * @return string|null The plain text, or null when empty.
     */
    private static function plain_text_value($raw, \core\context\course $context): ?string {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        return format_string($raw, true, ['context' => $context, 'escape' => false]);
    }

    /**
     * Append the hybrid option to the modality select of a site provisioned before it.
     *
     * The option texts were written into the field's configdata when it was created, and
     * {@see ensure_fields()} never rewrites a field that exists, so the third line has to be
     * appended here. It is appended only to a select that still holds exactly the two lines it
     * was created with: the stored value of an option is its position, so a third line an
     * administrator added would become "hybrid" if this step appended a fourth one, and one
     * edited into place already is the administrator's to keep. Written with a plain update for
     * the reason {@see retire_public_field()} gives.
     *
     * @return bool Whether the option was appended.
     */
    public static function add_hybrid_option(): bool {
        global $DB;

        self::reset_field_cache();
        $field = self::get_field(self::SHORTNAME_MODALITY);
        if (!$field) {
            return false;
        }
        // The persistent hands configdata back decoded.
        $config = (array) $field->get('configdata');
        $lines = preg_split("/\s*\n\s*/", trim((string) ($config['options'] ?? '')));
        if (count(array_filter($lines, 'strlen')) !== 2) {
            return false;
        }
        $lines[] = get_string('landingfield_modality_hybrid', self::COMPONENT);
        $config['options'] = implode("\n", $lines);
        $DB->update_record('customfield_field', (object) [
            'id' => $field->get('id'),
            'configdata' => json_encode($config),
            'timemodified' => time(),
        ]);
        self::reset_field_cache();
        return true;
    }

    /**
     * Append the trail option to the page model select of a site provisioned before it.
     *
     * The same step as {@see add_hybrid_option()}, for the same reasons: the line is appended
     * only to a select that still holds exactly the two lines it was created with (course and
     * event), because the stored value of an option is its position.
     *
     * @return bool Whether the option was appended.
     */
    public static function add_trail_option(): bool {
        global $DB;

        self::reset_field_cache();
        $field = self::get_field(self::SHORTNAME_MODEL);
        if (!$field) {
            return false;
        }
        $config = (array) $field->get('configdata');
        $lines = preg_split("/\s*\n\s*/", trim((string) ($config['options'] ?? '')));
        if (count(array_filter($lines, 'strlen')) !== 2) {
            return false;
        }
        $lines[] = get_string('landingfield_model_trail', self::COMPONENT);
        $config['options'] = implode("\n", $lines);
        $DB->update_record('customfield_field', (object) [
            'id' => $field->get('id'),
            'configdata' => json_encode($config),
            'timemodified' => time(),
        ]);
        self::reset_field_cache();
        return true;
    }

    /**
     * Move the format, live class and in-person fields to their place right after the workload.
     *
     * {@see ensure_fields()} appends a field it creates to the end of the category, which on a
     * site provisioned before these fields existed puts the format and the live classes after the share
     * picture. Each field is moved ahead of the target audience field, the one that follows them
     * in {@see self::definitions()}, when both sit in the same category;
     * moved one after the other in their own order, they land in that order. A field an
     * administrator has moved to another category is left where it is.
     *
     * @return void
     */
    public static function place_format_fields(): void {
        self::reset_field_cache();
        $anchor = self::get_field(self::SHORTNAME_AUDIENCE);
        if (!$anchor) {
            return;
        }
        foreach (self::ROUND6_SHORTNAMES as $shortname) {
            $field = self::get_field($shortname);
            if ($field && (int) $field->get('categoryid') === (int) $anchor->get('categoryid')) {
                api::move_field($field, (int) $anchor->get('categoryid'), (int) $anchor->get('id'));
            }
        }
        self::reset_field_cache();
    }

    /**
     * Delete the retired "public enrolment page" checkbox and its data.
     *
     * Its meaning moved into local_unlistedcourses' discoverability state,
     * where publishing is a manager's act behind a capability; a checkbox
     * that any course editor could tick would be a way around that gate, and
     * left in the form it would be a control that does nothing. Nothing is
     * migrated: a course is published by somebody who may, not by an
     * upgrade. Plain deletes, because the customfield API's delete path is
     * built for a request with an acting user and the handler's cache reset
     * is test-only code that throws during an upgrade.
     *
     * The query matches every checkbox of the retired shortname in the course
     * custom fields, in any category, not only in the theme's own. That is
     * acceptable for a one-off call: the shortname belongs to the field
     * this theme created, so a field of that name and type is the retired one
     * wherever an administrator may have moved it.
     *
     * @return void
     */
    public static function retire_public_field(): void {
        global $DB;

        $sql = "SELECT f.id
                  FROM {customfield_field} f
                  JOIN {customfield_category} c ON c.id = f.categoryid
                 WHERE c.component = :component
                   AND c.area = :area
                   AND f.shortname = :shortname
                   AND f.type = :type";
        $fieldids = $DB->get_fieldset_sql($sql, [
            'component' => 'core_course',
            'area' => 'course',
            'shortname' => self::SHORTNAME_RETIRED_PUBLIC,
            'type' => 'checkbox',
        ]);
        foreach ($fieldids as $fieldid) {
            $DB->delete_records('customfield_data', ['fieldid' => $fieldid]);
            $DB->delete_records('customfield_field', ['id' => $fieldid]);
        }
        self::reset_field_cache();
    }

    /**
     * Resolve the theme's category, creating it when missing.
     *
     * The created category id is remembered in plugin config so renames by the
     * admin do not break the lookup; a stale id (category deleted) falls back
     * to creating a fresh category.
     *
     * @return category_controller|null The category controller.
     */
    private static function get_or_create_category(): ?category_controller {
        $categories = api::get_categories_with_fields('core_course', 'course', 0);

        $storedid = (int) get_config(self::COMPONENT, self::CATEGORYID_CONFIG);
        if ($storedid) {
            foreach ($categories as $category) {
                if ((int) $category->get('id') === $storedid) {
                    return $category;
                }
            }
        }

        $handler = course_handler::create();
        $categoryid = $handler->create_category(get_string('landingfield_category', self::COMPONENT));
        set_config(self::CATEGORYID_CONFIG, $categoryid, self::COMPONENT);
        return category_controller::create($categoryid);
    }
}
