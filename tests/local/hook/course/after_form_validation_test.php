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
 * Theme Dimensions - Tests for the after_form_validation hook callback
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\course;

use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\landingfields;

/**
 * The video address is refused when no enabled media player embeds it.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(after_form_validation::class)]
final class after_form_validation_test extends \advanced_testcase {
    /**
     * Run the callback over one submission and return the errors it added.
     *
     * @param array $data The submitted data.
     * @return array Errors keyed by element name.
     */
    private function errors_for(array $data): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/edit_form.php');

        $hook = new \core_course\hook\after_form_validation($this->createMock(\course_edit_form::class), $data, []);
        after_form_validation::callback($hook);
        return $hook->get_errors();
    }

    /**
     * A YouTube address passes, an empty field passes, and a form without the field is left alone.
     */
    public function test_embeddable_and_empty_addresses_pass(): void {
        $this->resetAfterTest();
        $element = 'customfield_' . landingfields::SHORTNAME_VIDEOURL;

        // The precondition the pass depends on: YouTube is an enabled player on this site.
        $this->assertContains('youtube', array_keys(\core\plugininfo\media::get_enabled_plugins()));

        $this->assertSame([], $this->errors_for([$element => 'https://www.youtube.com/watch?v=Fs7Aph2027x']));
        $this->assertSame([], $this->errors_for([$element => 'https://youtu.be/Fs7Aph2027x']));
        $this->assertSame([], $this->errors_for([$element => '   ']));
        $this->assertSame([], $this->errors_for(['fullname' => 'No landing page fields in this form']));
    }

    /**
     * An address no player embeds, and a string that is not a URL, are refused on the field.
     */
    public function test_other_addresses_are_refused_on_the_field(): void {
        $this->resetAfterTest();
        $element = 'customfield_' . landingfields::SHORTNAME_VIDEOURL;
        $expected = [$element => get_string('landing_videourlinvalid', landingfields::COMPONENT)];

        $this->assertSame($expected, $this->errors_for([$element => 'https://example.com/apresentacao.html']));
        $this->assertSame($expected, $this->errors_for([$element => 'javascript:alert(1)']));
        $this->assertSame($expected, $this->errors_for([$element => 'not an address']));
    }

    /**
     * Disabling every player turns a pass into a refusal: the check is live, not a list of hosts.
     *
     * Every player has to go, not only media_youtube: media_videojs embeds YouTube
     * addresses as well (its own "youtube" setting, on by default).
     */
    public function test_the_check_follows_the_enabled_players(): void {
        $this->resetAfterTest();
        $element = 'customfield_' . landingfields::SHORTNAME_VIDEOURL;
        $url = 'https://www.youtube.com/watch?v=Fs7Aph2027x';

        $this->assertSame([], $this->errors_for([$element => $url]));

        \core\plugininfo\media::set_enabled_plugins('');
        \core_media_manager::reset_caches();
        $this->assertArrayHasKey($element, $this->errors_for([$element => $url]));
    }
    /**
     * A submission of the round-6 fields, keyed as the course form posts them.
     *
     * @param int $model The page model.
     * @param int $modality The modality.
     * @param int $format The format.
     * @param string $total The total workload as typed.
     * @param string $inperson The in-person workload as typed.
     * @return array The submitted data.
     */
    private static function round6(int $model, int $modality, int $format, string $total = '', string $inperson = ''): array {
        return [
            'customfield_' . landingfields::SHORTNAME_MODEL => (string) $model,
            'customfield_' . landingfields::SHORTNAME_MODALITY => (string) $modality,
            'customfield_' . landingfields::SHORTNAME_FORMAT => (string) $format,
            'customfield_' . landingfields::SHORTNAME_WORKLOAD => $total,
            'customfield_' . landingfields::SHORTNAME_INPERSONWORKLOAD => $inperson,
        ];
    }

    /**
     * A self-paced course that is not online is refused on the modality; online, or tutored, it passes.
     */
    public function test_a_self_paced_course_must_be_online(): void {
        $this->resetAfterTest();
        $c = landingfields::MODEL_COURSE;
        $sp = landingfields::FORMAT_SELFPACED;
        $tu = landingfields::FORMAT_TUTORED;
        $expected = [
            'customfield_' . landingfields::SHORTNAME_MODALITY => get_string('landing_selfpacedonline', landingfields::COMPONENT),
        ];

        $this->assertSame($expected, $this->errors_for(self::round6($c, landingfields::MODALITY_INPERSON, $sp)));
        $this->assertSame($expected, $this->errors_for(self::round6($c, landingfields::MODALITY_HYBRID, $sp)));
        // No model yet: the format is visible all the same, and so is the refusal.
        $this->assertSame($expected, $this->errors_for(self::round6(0, landingfields::MODALITY_HYBRID, $sp)));

        $this->assertSame([], $this->errors_for(self::round6($c, landingfields::MODALITY_ONLINE, $sp)));
        $this->assertSame([], $this->errors_for(self::round6($c, landingfields::MODALITY_HYBRID, $tu)));
        $this->assertSame([], $this->errors_for(self::round6($c, landingfields::MODALITY_INPERSON, 0)));
    }

    /**
     * On an event the format is hidden, so a self-paced value it kept from before is not refused.
     *
     * An error on a hidden field stops the save with nothing on screen to fix; the page reads the
     * value away instead (landingfields::coherent()).
     */
    public function test_a_hidden_format_on_an_event_is_not_refused(): void {
        $this->resetAfterTest();
        $data = self::round6(
            landingfields::MODEL_EVENT,
            landingfields::MODALITY_HYBRID,
            landingfields::FORMAT_SELFPACED
        );
        $this->assertSame([], $this->errors_for($data));
    }

    /**
     * A hybrid offering's in-person hours may equal the total and may not pass it.
     */
    public function test_in_person_hours_fit_inside_the_total_of_a_hybrid_offering(): void {
        $this->resetAfterTest();
        $c = landingfields::MODEL_COURSE;
        $hy = landingfields::MODALITY_HYBRID;
        $tu = landingfields::FORMAT_TUTORED;
        $element = 'customfield_' . landingfields::SHORTNAME_INPERSONWORKLOAD;

        $errors = $this->errors_for(self::round6($c, $hy, $tu, '60', '61'));
        $this->assertSame([$element], array_keys($errors));
        $this->assertStringContainsString('60', $errors[$element]);

        $this->assertSame([], $this->errors_for(self::round6($c, $hy, $tu, '60', '60')));
        $this->assertSame([], $this->errors_for(self::round6($c, $hy, $tu, '60', '16')));
        // Nothing to exceed without a total, and the hidden field of another modality is not read.
        $this->assertSame([], $this->errors_for(self::round6($c, $hy, $tu, '', '16')));
        $this->assertSame([], $this->errors_for(self::round6($c, landingfields::MODALITY_ONLINE, $tu, '60', '61')));
    }
}
