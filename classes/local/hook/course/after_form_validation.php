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
 * Theme Dimensions - Course form after_form_validation hook callback
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\course;

use theme_dimensions\local\hotsitefields;

/**
 * Validates the hotsite fields of the course settings form.
 *
 * Three rules. The video address: the hotsite embeds it through core's
 * media manager, so an address no enabled media player handles would save
 * fine and then render as nothing. Refusing it here, with the field named,
 * is the only moment the editor is looking. The check is the same the page
 * will make, {@see embeddable()}, so the two cannot drift apart. And two of
 * round 6: a self-paced course is always online (decision D10), and a hybrid
 * offering's in-person hours fit inside its total (decision D8).
 *
 * Only what the editor can see is refused. A field the form hides
 * ({@see after_form_definition::hide_inapplicable_fields()}) keeps its value and is
 * submitted with it; an error on it would stop the save with nothing on screen to fix, so
 * the page reads such values away instead ({@see hotsitefields::coherent()}).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class after_form_validation {
    /**
     * Refuse a video address no enabled media player embeds.
     *
     * @param \core_course\hook\after_form_validation $hook The hook.
     * @return void
     */
    public static function callback(\core_course\hook\after_form_validation $hook): void {
        $hook->add_errors(self::round6_errors($hook->get_data()));
        $element = 'customfield_' . hotsitefields::SHORTNAME_VIDEOURL;
        $data = $hook->get_data();
        if (!isset($data[$element])) {
            return;
        }
        $url = trim((string) $data[$element]);
        if ($url === '' || self::embeddable($url)) {
            return;
        }
        $hook->add_errors([$element => get_string('hotsite_videourlinvalid', hotsitefields::COMPONENT)]);
    }

    /**
     * The round-6 refusals: a self-paced course that is not online, in-person hours past the total.
     *
     * The format is visible unless the model is an event, so a self-paced format is refused
     * beside an in-person or hybrid modality on any other model, the error on the modality,
     * which is the one of the two that names what a self-paced course must be. The in-person
     * workload is visible only on a hybrid offering, so it is compared with the total only there,
     * and only when both are numbers (an empty total leaves nothing to exceed).
     *
     * @param array $data The submitted data.
     * @return array Errors keyed by element name, empty when the submission passes.
     */
    private static function round6_errors(array $data): array {
        $modelo = (int) ($data['customfield_' . hotsitefields::SHORTNAME_MODELO] ?? 0);
        $modalidade = (int) ($data['customfield_' . hotsitefields::SHORTNAME_MODALIDADE] ?? 0);
        $formato = (int) ($data['customfield_' . hotsitefields::SHORTNAME_FORMATO] ?? 0);
        $errors = [];

        $notonline = in_array($modalidade, [hotsitefields::MODALIDADE_PRESENCIAL, hotsitefields::MODALIDADE_HIBRIDO], true);
        if ($modelo !== hotsitefields::MODELO_EVENTO && $formato === hotsitefields::FORMATO_AUTOINSTRUCIONAL && $notonline) {
            $errors['customfield_' . hotsitefields::SHORTNAME_MODALIDADE] =
                get_string('hotsite_selfpacedonline', hotsitefields::COMPONENT);
        }

        if ($modalidade === hotsitefields::MODALIDADE_HIBRIDO) {
            $total = $data['customfield_' . hotsitefields::SHORTNAME_CARGAHORARIA] ?? '';
            $inperson = $data['customfield_' . hotsitefields::SHORTNAME_CARGAPRESENCIAL] ?? '';
            if (is_numeric($total) && is_numeric($inperson) && (float) $total > 0 && (float) $inperson > (float) $total) {
                $errors['customfield_' . hotsitefields::SHORTNAME_CARGAPRESENCIAL] =
                    get_string('hotsite_cargapresencialtoobig', hotsitefields::COMPONENT, format_float((float) $total, -1));
            }
        }
        return $errors;
    }

    /**
     * Whether an address is a URL that an enabled media player embeds.
     *
     * @param string $url The address as typed.
     * @return bool Whether the page will be able to embed it.
     */
    public static function embeddable(string $url): bool {
        if ($url === '' || clean_param($url, PARAM_URL) !== $url) {
            return false;
        }
        return \core_media_manager::instance()->can_embed_url(new \moodle_url($url));
    }
}
