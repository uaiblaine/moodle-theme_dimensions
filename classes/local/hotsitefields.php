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
 * Theme Dimensions - Hotsite course custom fields
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
 * Provisions the course custom fields the enrolment hotsite reads.
 *
 * The theme owns the display, not the data: the "Hotsite" category and its
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
class hotsitefields {
    /** @var string Component name, used for config reads and the lock namespace. */
    public const COMPONENT = 'theme_dimensions';

    /** @var string Plugin config key remembering the provisioned category id. */
    public const CATEGORYID_CONFIG = 'hotsitecfcategoryid';

    /** @var string Shortname of the page model select (curso/evento). */
    public const SHORTNAME_MODELO = 'hotsite_modelo';

    /** @var string Shortname of the modality select (online/presencial/hibrido). */
    public const SHORTNAME_MODALIDADE = 'modalidade';

    /** @var string Shortname of the workload number field. */
    public const SHORTNAME_CARGAHORARIA = 'carga_horaria';

    /** @var string Shortname of the target audience textarea. */
    public const SHORTNAME_PUBLICOALVO = 'publico_alvo';

    /** @var string Shortname of the format select (self-paced/tutored), courses only. */
    public const SHORTNAME_FORMATO = 'formato';

    /** @var string Shortname of the live classes checkbox, tutored courses only. */
    public const SHORTNAME_AULASAOVIVO = 'aulas_aovivo';

    /** @var string Shortname of the text saying when the live classes happen. */
    public const SHORTNAME_AULASAOVIVOQUANDO = 'aulas_aovivo_quando';

    /** @var string Shortname of the in-person workload number field, hybrid offerings only. */
    public const SHORTNAME_CARGAPRESENCIAL = 'carga_presencial';

    /** @var string Shortname of the in-person meetings text field, hybrid offerings only. */
    public const SHORTNAME_ENCONTROS = 'encontros_presenciais';

    /**
     * @var string[] The fields of round 6, in the order they sit right after the workload.
     *
     * A site that had the other fields before them gets them appended at the end of the category
     * by {@see self::ensure_fields()}; the upgrade then moves them here with
     * {@see self::place_format_fields()}, so that an upgraded site lists the fields in the same
     * order as a fresh one.
     */
    public const ROUND6_SHORTNAMES = [
        self::SHORTNAME_FORMATO,
        self::SHORTNAME_AULASAOVIVO,
        self::SHORTNAME_AULASAOVIVOQUANDO,
        self::SHORTNAME_CARGAPRESENCIAL,
        self::SHORTNAME_ENCONTROS,
    ];

    /** @var string Shortname of the programme textarea. */
    public const SHORTNAME_PROGRAMA = 'programa';

    /** @var string Shortname of the certification textarea. */
    public const SHORTNAME_CERTIFICACAO = 'certificacao';

    /** @var string Shortname of the city text field (free text). */
    public const SHORTNAME_LOCALCIDADE = 'local_cidade';

    /** @var string Shortname of the venue name text field. */
    public const SHORTNAME_LOCALNOME = 'local_nome';

    /** @var string Shortname of the venue address text field. */
    public const SHORTNAME_LOCALENDERECO = 'local_endereco';

    /** @var string Shortname of the public description text field (never displayed; read for the Open Graph tags). */
    public const SHORTNAME_DESCRICAOPUBLICA = 'descricao_publica';

    /** @var string Shortname of the video URL text field (YouTube or Vimeo, embedded by the page). */
    public const SHORTNAME_VIDEOURL = 'video_url';

    /** @var string Shortname of the first badge picture field. */
    public const SHORTNAME_BRASAO1 = 'brasao_1';

    /** @var string Shortname of the second badge picture field. */
    public const SHORTNAME_BRASAO2 = 'brasao_2';

    /** @var string Shortname of the third badge picture field. */
    public const SHORTNAME_BRASAO3 = 'brasao_3';

    /** @var string Shortname of the fourth badge picture field. */
    public const SHORTNAME_BRASAO4 = 'brasao_4';

    /** @var string[] The badge fields in display order: the hero's grid reads left to right, top to bottom. */
    public const BRASAO_SHORTNAMES = [
        self::SHORTNAME_BRASAO1,
        self::SHORTNAME_BRASAO2,
        self::SHORTNAME_BRASAO3,
        self::SHORTNAME_BRASAO4,
    ];

    /** @var int Largest badge file accepted, in bytes: a 784px PNG with alpha is about 300 KB, an SVG far less. */
    public const BRASAO_MAXBYTES = 2097152;

    /** @var string The custom field type of the badge fields, provided by customfield_picture. */
    public const BRASAO_TYPE = 'picture';

    /** @var string Shortname of the share picture field (never displayed; the source of the hotsite's og:image). */
    public const SHORTNAME_IMAGEMPUBLICA = 'imagem_publica';

    /**
     * @var int Largest share picture accepted, in bytes.
     *
     * The picture is a SOURCE, never served as it is: the Open Graph route serves a 1200x630
     * derivative of it, and {@see hotsite\publicfiles::MAX_SOURCE_PIXELS} is what bounds the work of
     * making one. So the cap only has to admit a photo straight from a camera, which the badges'
     * 2 MB does not.
     */
    public const IMAGEMPUBLICA_MAXBYTES = 8388608;

    /**
     * @var string[] Every picture field of the theme, the badges and the share picture.
     *
     * All of them are visible to nobody, so core's handler backs none of their files up: the
     * theme's backup and restore plugin classes carry the rows and the files of exactly this list.
     */
    public const PICTURE_SHORTNAMES = [
        self::SHORTNAME_BRASAO1,
        self::SHORTNAME_BRASAO2,
        self::SHORTNAME_BRASAO3,
        self::SHORTNAME_BRASAO4,
        self::SHORTNAME_IMAGEMPUBLICA,
    ];

    /** @var string Shortname of the retired "public hotsite" checkbox, kept only so the upgrade can find it. */
    public const SHORTNAME_RETIRED_PUBLICO = 'hotsite_publico';

    /** @var int Stored value of the page model select for "course". */
    public const MODELO_CURSO = 1;

    /** @var int Stored value of the page model select for "event". */
    public const MODELO_EVENTO = 2;

    /** @var int Stored value of the modality select for "online". */
    public const MODALIDADE_ONLINE = 1;

    /** @var int Stored value of the modality select for "in-person". */
    public const MODALIDADE_PRESENCIAL = 2;

    /** @var int Stored value of the modality select for "hybrid", the line appended in round 6. */
    public const MODALIDADE_HIBRIDO = 3;

    /** @var int Stored value of the format select for "self-paced". */
    public const FORMATO_AUTOINSTRUCIONAL = 1;

    /** @var int Stored value of the format select for "tutored". */
    public const FORMATO_TUTORIA = 2;

    /** @var array|null Request cache of resolved field controllers keyed by shortname (null = missing). */
    private static ?array $fieldcache = null;

    /**
     * The full definition of every field this theme provisions.
     *
     * Single source of truth for provisioning and for the tests. Each entry
     * carries the customfield type, the lang string key of its name (the
     * description uses the same key with a '_desc' suffix) and the
     * type-specific configdata.
     *
     * Select values are stored as a 1-based index into the options list
     * (core prepends an empty choice at index 0), so the MODELO_*,
     * MODALIDADE_* and FORMATO_* constants are bound to the ORDER of the lines
     * in the 'options' strings below. Never reorder them; a new option goes at
     * the end, and a site provisioned before it needs an upgrade step that
     * appends it, as {@see add_hybrid_option()} does for the hybrid modality.
     *
     * @return array Map of shortname => ['type' => string, 'stringkey' => string, 'config' => array].
     */
    public static function definitions(): array {
        return [
            self::SHORTNAME_MODELO => [
                'type' => 'select',
                'stringkey' => 'hotsitefield_modelo',
                'config' => [
                    'options' => get_string('hotsitefield_modelo_curso', self::COMPONENT) . "\n" .
                        get_string('hotsitefield_modelo_evento', self::COMPONENT),
                    'defaultvalue' => '',
                ],
            ],
            self::SHORTNAME_MODALIDADE => [
                'type' => 'select',
                'stringkey' => 'hotsitefield_modalidade',
                'config' => [
                    'options' => get_string('hotsitefield_modalidade_online', self::COMPONENT) . "\n" .
                        get_string('hotsitefield_modalidade_presencial', self::COMPONENT) . "\n" .
                        get_string('hotsitefield_modalidade_hibrido', self::COMPONENT),
                    'defaultvalue' => '',
                ],
            ],
            self::SHORTNAME_CARGAHORARIA => [
                'type' => 'number',
                'stringkey' => 'hotsitefield_cargahoraria',
                'config' => [
                    'decimalplaces' => 0,
                    'minimumvalue' => 0,
                    'maximumvalue' => '',
                    'display' => '{value}',
                    'displaywhenzero' => '0',
                    'fieldtype' => '',
                ],
            ],
            self::SHORTNAME_FORMATO => [
                'type' => 'select',
                'stringkey' => 'hotsitefield_formato',
                'config' => [
                    'options' => get_string('hotsitefield_formato_autoinstrucional', self::COMPONENT) . "\n" .
                        get_string('hotsitefield_formato_tutoria', self::COMPONENT),
                    'defaultvalue' => '',
                ],
            ],
            self::SHORTNAME_AULASAOVIVO => [
                'type' => 'checkbox',
                'stringkey' => 'hotsitefield_aulasaovivo',
                'config' => [
                    'checkbydefault' => 0,
                ],
            ],
            self::SHORTNAME_AULASAOVIVOQUANDO => [
                'type' => 'text',
                'stringkey' => 'hotsitefield_aulasaovivoquando',
                'config' => [
                    'displaysize' => 50,
                    'maxlength' => 120,
                    'ispassword' => 0,
                    'link' => '',
                    'linktarget' => '',
                ],
            ],
            self::SHORTNAME_CARGAPRESENCIAL => [
                'type' => 'number',
                'stringkey' => 'hotsitefield_cargapresencial',
                'config' => [
                    'decimalplaces' => 0,
                    'minimumvalue' => 0,
                    'maximumvalue' => '',
                    'display' => '{value}',
                    'displaywhenzero' => '0',
                    'fieldtype' => '',
                ],
            ],
            self::SHORTNAME_ENCONTROS => [
                'type' => 'text',
                'stringkey' => 'hotsitefield_encontros',
                'config' => [
                    'displaysize' => 50,
                    'maxlength' => 120,
                    'ispassword' => 0,
                    'link' => '',
                    'linktarget' => '',
                ],
            ],
            self::SHORTNAME_PUBLICOALVO => [
                'type' => 'textarea',
                'stringkey' => 'hotsitefield_publicoalvo',
                'config' => [
                    'defaultvalue' => '',
                    'defaultvalueformat' => FORMAT_HTML,
                ],
            ],
            self::SHORTNAME_PROGRAMA => [
                'type' => 'textarea',
                'stringkey' => 'hotsitefield_programa',
                'config' => [
                    'defaultvalue' => '',
                    'defaultvalueformat' => FORMAT_HTML,
                ],
            ],
            self::SHORTNAME_CERTIFICACAO => [
                'type' => 'textarea',
                'stringkey' => 'hotsitefield_certificacao',
                'config' => [
                    'defaultvalue' => '',
                    'defaultvalueformat' => FORMAT_HTML,
                ],
            ],
            self::SHORTNAME_LOCALCIDADE => [
                'type' => 'text',
                'stringkey' => 'hotsitefield_localcidade',
                'config' => [
                    'displaysize' => 50,
                    'maxlength' => 120,
                    'ispassword' => 0,
                    'link' => '',
                    'linktarget' => '',
                ],
            ],
            self::SHORTNAME_LOCALNOME => [
                'type' => 'text',
                'stringkey' => 'hotsitefield_localnome',
                'config' => [
                    'displaysize' => 50,
                    'maxlength' => 255,
                    'ispassword' => 0,
                    'link' => '',
                    'linktarget' => '',
                ],
            ],
            self::SHORTNAME_LOCALENDERECO => [
                'type' => 'text',
                'stringkey' => 'hotsitefield_localendereco',
                'config' => [
                    'displaysize' => 50,
                    'maxlength' => 255,
                    'ispassword' => 0,
                    'link' => '',
                    'linktarget' => '',
                ],
            ],
            self::SHORTNAME_DESCRICAOPUBLICA => [
                'type' => 'text',
                'stringkey' => 'hotsitefield_descricaopublica',
                'config' => [
                    'displaysize' => 50,
                    /* Open Graph descriptions are cut at about 200 characters by every
                       consumer that matters; the limit is enforced where it is typed. */
                    'maxlength' => 200,
                    'ispassword' => 0,
                    'link' => '',
                    'linktarget' => '',
                    /* Never displayed on a course card - it exists for the og:description of
                       the public hotsite, and it is the reviewed text for an outside audience,
                       never the course summary. */
                    'visibility' => course_handler::NOTVISIBLE,
                ],
            ],
        ] + self::badge_definitions() + [
            self::SHORTNAME_VIDEOURL => [
                'type' => 'text',
                'stringkey' => 'hotsitefield_videourl',
                'config' => [
                    'displaysize' => 50,
                    'maxlength' => 255,
                    'ispassword' => 0,
                    'link' => '',
                    'linktarget' => '',
                    // The hotsite embeds it; a bare address on a course card would be noise.
                    'visibility' => course_handler::NOTVISIBLE,
                ],
            ],
            // Last, so that a fresh site and an upgraded one list the fields in the same order.
            self::SHORTNAME_IMAGEMPUBLICA => [
                'type' => self::BRASAO_TYPE,
                'stringkey' => 'hotsitefield_imagempublica',
                'config' => [
                    'maximumbytes' => self::IMAGEMPUBLICA_MAXBYTES,
                    /* Never displayed on the platform - it exists for the og:image of the public
                       hotsite, the pair of the public description above. */
                    'visibility' => course_handler::NOTVISIBLE,
                ],
            ],
        ];
    }

    /**
     * The definitions of the four badge picture fields, one per grid position.
     *
     * Visible to nobody on purpose: the hotsite is their only display, and a
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
        foreach (self::BRASAO_SHORTNAMES as $position => $shortname) {
            $definitions[$shortname] = [
                'type' => self::BRASAO_TYPE,
                'stringkey' => 'hotsitefield_brasao' . ($position + 1),
                'config' => [
                    'maximumbytes' => self::BRASAO_MAXBYTES,
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
        if ($definition['type'] !== self::BRASAO_TYPE) {
            return true;
        }
        return self::picture_type_available();
    }

    /**
     * Whether customfield_picture is installed and enabled, so the five picture fields can exist.
     *
     * The plugin is recommended, not required. Without it the badges and the share picture are not
     * provisioned, and every reader of them ({@see pictures()}, {@see typed_values()}) answers
     * "none" through its instanceof check, so the hotsite and the category listing draw without
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
        return \core_component::get_plugin_directory('customfield', self::BRASAO_TYPE) !== null
            && empty(get_config('customfield_' . self::BRASAO_TYPE, 'disabled'));
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
     */
    public static function get_field(string $shortname): ?field_controller {
        $definitions = self::definitions();
        if (!isset($definitions[$shortname])) {
            throw new \coding_exception("Unknown hotsite field shortname '{$shortname}'.");
        }
        if (self::$fieldcache === null) {
            self::load_field_cache();
        }
        return self::$fieldcache[$shortname];
    }

    /**
     * Reset the request-level field cache (used by tests and after provisioning).
     *
     * @return void
     */
    public static function reset_field_cache(): void {
        self::$fieldcache = null;
    }

    /**
     * Ensure the hotsite course custom fields exist, creating the missing ones.
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
        $lock = $lockfactory->get_lock('hotsitefields', 10);
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
        $definitions = self::definitions();
        self::$fieldcache = array_fill_keys(array_keys($definitions), null);

        $categories = api::get_categories_with_fields('core_course', 'course', 0);
        foreach ($categories as $category) {
            foreach ($category->get_fields() as $field) {
                $shortname = $field->get('shortname');
                if (!array_key_exists($shortname, self::$fieldcache)) {
                    continue;
                }
                if ($field->get('type') !== $definitions[$shortname]['type']) {
                    debugging(
                        self::COMPONENT . ": field '{$shortname}' exists with unexpected type '" .
                            $field->get('type') . "', expected '{$definitions[$shortname]['type']}' - not used.",
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
     * Read every hotsite field value of one course, typed for the page.
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
     * The fields of round 6 arrive already read without contradiction (decision D10,
     * {@see coherent()}): a page never has to ask whether a format, a live class or an
     * in-person figure may be shown beside the model and the modality it was saved with.
     *
     * @return \stdClass Typed values: modelo/modalidade/formato (int, 0 = unset), cargahoraria/
     *   cargapresencial (float|null), aovivo (bool), publicoalvo/programa/certificacao (HTML
     *   string|null), cidade/localnome/endereco/descricaopublica/videourl/aovivoquando/encontros
     *   (plain string|null), brasoes (array of \stored_file in grid order), names (array of
     *   shortname => plain field name).
     */
    public static function values(int $courseid): \stdClass {
        return self::values_many([$courseid], true)[$courseid];
    }

    /**
     * Read every hotsite field value of many courses, typed for the page, in one statement.
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
     * A listing must not format the textareas the way the hotsite does. Core's
     * textarea export runs format_text() with the text filters on, in the
     * course's context, and the first format_text() in a context loads that
     * context's active filters (a recordset) and then lets every active filter
     * run - filter_activitynames alone reads the course record for each context
     * it meets. On a page of 48 cards that is dozens of statements the card's
     * excerpt cannot justify, so a listing asks for the textareas with $filters
     * false: the same cleaning, the same pluginfile rewriting, no filter pass.
     * The hotsite keeps the full, filtered rendering. The rewrite mirrors
     * customfield_textarea's data_controller::export_value() and must be
     * re-read against it on each core upgrade.
     *
     * @param int[] $courseids The courses.
     * @param bool $filters Whether the textareas go through the text filters (the hotsite) or not (a listing).
     * @return \stdClass[] courseid => the typed values of values(), for every id given.
     */
    public static function values_many(array $courseids, bool $filters = true): array {
        global $DB;

        $courseids = array_values(array_unique(array_map('intval', $courseids)));
        $results = [];
        if (!$courseids) {
            return $results;
        }

        $definitions = self::definitions();
        $fields = [];
        foreach (course_handler::create()->get_fields() as $field) {
            $shortname = $field->get('shortname');
            if (isset($definitions[$shortname]) && $field->get('type') === $definitions[$shortname]['type']) {
                $fields[(int) $field->get('id')] = $field;
            }
        }

        $data = array_fill_keys($courseids, []);
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
                $data[(int) $row->instanceid][(int) $row->fieldid] = data_controller::create(0, $row, $fields[(int) $row->fieldid]);
            }
        }

        foreach ($courseids as $courseid) {
            $controllers = [];
            foreach ($fields as $fieldid => $field) {
                $controllers[] = $data[$courseid][$fieldid]
                    ?? data_controller::create(0, (object) ['instanceid' => $courseid], $field);
            }
            $results[$courseid] = self::typed_values($courseid, $controllers, $definitions, $filters);
        }
        return $results;
    }

    /**
     * Turn one course's data controllers into the typed values of values().
     *
     * @param int $courseid The course id.
     * @param data_controller[] $controllers One controller per hotsite field, defaults included.
     * @param array $definitions The field definitions, shortname => definition.
     * @param bool $filters Whether the textareas go through the text filters.
     * @return \stdClass The typed values.
     */
    private static function typed_values(int $courseid, array $controllers, array $definitions, bool $filters): \stdClass {
        $values = (object) [
            'modelo' => 0,
            'modalidade' => 0,
            'formato' => 0,
            'aovivo' => false,
            'aovivoquando' => null,
            'cargahoraria' => null,
            'cargapresencial' => null,
            'encontros' => null,
            'publicoalvo' => null,
            'programa' => null,
            'certificacao' => null,
            'cidade' => null,
            'localnome' => null,
            'endereco' => null,
            'descricaopublica' => null,
            'videourl' => null,
            'brasoes' => [],
            'names' => [],
        ];
        $badges = [];

        $context = \core\context\course::instance($courseid);
        foreach ($controllers as $data) {
            $field = $data->get_field();
            $shortname = $field->get('shortname');
            if (!isset($definitions[$shortname]) || $field->get('type') !== $definitions[$shortname]['type']) {
                continue;
            }
            $values->names[$shortname] = format_string($field->get('name'), true, [
                'context' => $context,
                'escape' => false,
            ]);

            switch ($shortname) {
                case self::SHORTNAME_MODELO:
                    $values->modelo = (int) $data->get_value();
                    break;
                case self::SHORTNAME_MODALIDADE:
                    $values->modalidade = (int) $data->get_value();
                    break;
                case self::SHORTNAME_CARGAHORARIA:
                    $values->cargahoraria = self::hours_value($data->get_value());
                    break;
                case self::SHORTNAME_FORMATO:
                    $values->formato = (int) $data->get_value();
                    break;
                case self::SHORTNAME_AULASAOVIVO:
                    $values->aovivo = (bool) $data->get_value();
                    break;
                case self::SHORTNAME_AULASAOVIVOQUANDO:
                    $values->aovivoquando = self::plain_text_value($data->get_value(), $context);
                    break;
                case self::SHORTNAME_CARGAPRESENCIAL:
                    $values->cargapresencial = self::hours_value($data->get_value());
                    break;
                case self::SHORTNAME_ENCONTROS:
                    $values->encontros = self::plain_text_value($data->get_value(), $context);
                    break;
                case self::SHORTNAME_PUBLICOALVO:
                    $values->publicoalvo = self::textarea_value($data, $context, $filters);
                    break;
                case self::SHORTNAME_PROGRAMA:
                    $values->programa = self::textarea_value($data, $context, $filters);
                    break;
                case self::SHORTNAME_CERTIFICACAO:
                    $values->certificacao = self::textarea_value($data, $context, $filters);
                    break;
                case self::SHORTNAME_LOCALCIDADE:
                    $values->cidade = self::plain_text_value($data->get_value(), $context);
                    break;
                case self::SHORTNAME_LOCALNOME:
                    $values->localnome = self::plain_text_value($data->get_value(), $context);
                    break;
                case self::SHORTNAME_LOCALENDERECO:
                    $values->endereco = self::plain_text_value($data->get_value(), $context);
                    break;
                case self::SHORTNAME_DESCRICAOPUBLICA:
                    $values->descricaopublica = self::plain_text_value($data->get_value(), $context);
                    break;
                case self::SHORTNAME_VIDEOURL:
                    $values->videourl = self::plain_text_value($data->get_value(), $context);
                    break;
                case self::SHORTNAME_BRASAO1:
                case self::SHORTNAME_BRASAO2:
                case self::SHORTNAME_BRASAO3:
                case self::SHORTNAME_BRASAO4:
                    // The customfield_picture controller knows where its file is; a foreign 'picture' type would not.
                    if ($data instanceof \customfield_picture\data_controller) {
                        $badges[$shortname] = $data->get_file();
                    }
                    break;
            }
        }
        foreach (self::BRASAO_SHORTNAMES as $shortname) {
            if (!empty($badges[$shortname])) {
                $values->brasoes[] = $badges[$shortname];
            }
        }
        return self::coherent($values);
    }

    /**
     * Read the round-6 values without contradiction (decision D10), whatever was stored.
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
        $evento = $values->modelo === self::MODELO_EVENTO;
        $selfpaced = $values->formato === self::FORMATO_AUTOINSTRUCIONAL;
        $hybrid = $values->modalidade === self::MODALIDADE_HIBRIDO;
        $notonline = in_array($values->modalidade, [self::MODALIDADE_PRESENCIAL, self::MODALIDADE_HIBRIDO], true);

        if ($evento || $selfpaced) {
            $values->aovivo = false;
        }
        if (!$values->aovivo) {
            $values->aovivoquando = null;
        }
        if (!$hybrid || $selfpaced) {
            $values->cargapresencial = null;
            $values->encontros = null;
        }
        if ($evento || ($selfpaced && $notonline)) {
            $values->formato = 0;
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
     * has no use for. Same source, same order as values()->brasoes.
     *
     * @param int $courseid The course id.
     * @return \stored_file[] The badges in grid order, gaps closed.
     */
    public static function badges(int $courseid): array {
        return array_values(self::pictures($courseid, self::BRASAO_SHORTNAMES));
    }

    /**
     * The share picture of a course: the source of its hotsite's og:image, when an editor chose one.
     *
     * Read alone, like {@see badges()}: the Open Graph route asks for it on every request for the
     * image, and a page render asks once, neither with any use for the rest of values().
     *
     * @param int $courseid The course id.
     * @return \stored_file|null The picture, or null when none was uploaded.
     */
    public static function share_picture(int $courseid): ?\stored_file {
        return self::pictures($courseid, [self::SHORTNAME_IMAGEMPUBLICA])[self::SHORTNAME_IMAGEMPUBLICA] ?? null;
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
                && $field->get('type') === self::BRASAO_TYPE
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
     * The page model of a course (MODELO_*), 0 when unset - read alone, without formatting anything.
     *
     * @param int $courseid The course id.
     * @return int The stored model value.
     */
    public static function model(int $courseid): int {
        foreach (course_handler::create()->get_instance_data($courseid, true) as $data) {
            $field = $data->get_field();
            if ($field->get('shortname') === self::SHORTNAME_MODELO && $field->get('type') === 'select') {
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
        $field = self::get_field($shortname);
        if (!$field || $field->get('type') !== 'select') {
            return null;
        }
        $options = preg_split("/\s*\n\s*/", trim((string) $field->get_configdata_property('options')));
        return $options[$value - 1] ?? null;
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
        $field = self::get_field(self::SHORTNAME_MODALIDADE);
        if (!$field) {
            return false;
        }
        // The persistent hands configdata back decoded.
        $config = (array) $field->get('configdata');
        $lines = preg_split("/\s*\n\s*/", trim((string) ($config['options'] ?? '')));
        if (count(array_filter($lines, 'strlen')) !== 2) {
            return false;
        }
        $lines[] = get_string('hotsitefield_modalidade_hibrido', self::COMPONENT);
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
     * Move the fields of round 6 to their place right after the workload.
     *
     * {@see ensure_fields()} appends a field it creates to the end of the category, which on a
     * site provisioned before round 6 puts the format and the live classes after the share
     * picture. Each field is moved ahead of the target audience field, the one that follows them
     * in {@see self::definitions()}, when both sit in the same category;
     * moved one after the other in their own order, they land in that order. A field an
     * administrator has moved to another category is left where it is.
     *
     * @return void
     */
    public static function place_format_fields(): void {
        self::reset_field_cache();
        $anchor = self::get_field(self::SHORTNAME_PUBLICOALVO);
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
            'shortname' => self::SHORTNAME_RETIRED_PUBLICO,
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
        $categoryid = $handler->create_category(get_string('hotsitefield_category', self::COMPONENT));
        set_config(self::CATEGORYID_CONFIG, $categoryid, self::COMPONENT);
        return category_controller::create($categoryid);
    }
}
