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

namespace theme_dimensions\local\hotsite;

use local_unlistedcourses\discoverability;
use theme_dimensions\local\hotsitefields;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the file route of the public hotsite.
 *
 * serve() itself sends bytes and stops the process, so it is not exercised here;
 * the checks it relies on are all in resolve(), which is. The route as a whole is
 * verified with real requests against a running site.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(publicfiles::class)]
final class publicfiles_test extends \advanced_testcase {
    /**
     * An image of the given size made by GD, JPEG unless asked for PNG.
     *
     * @param int $width The width.
     * @param int $height The height.
     * @param string $type image/jpeg or image/png.
     * @param bool $noise Fill with random pixels, the worst case for compression, instead of one colour.
     * @return string The bytes.
     */
    private static function image(int $width, int $height, string $type = 'image/jpeg', bool $noise = false): string {
        $image = imagecreatetruecolor($width, $height);
        if ($noise) {
            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    imagesetpixel($image, $x, $y, (mt_rand(0, 255) << 16) | (mt_rand(0, 255) << 8) | mt_rand(0, 255));
                }
            }
        } else {
            imagefill($image, 0, 0, imagecolorallocate($image, 7, 0, 147));
        }
        ob_start();
        if ($type === 'image/png') {
            imagepng($image);
        } else {
            imagejpeg($image);
        }
        imagedestroy($image);
        return (string) ob_get_clean();
    }

    /**
     * A landscape JPEG whose left half is white, carrying an EXIF orientation tag.
     *
     * GD writes no EXIF, so the APP1 segment is assembled by hand: a little-endian TIFF
     * with IFD0 holding the orientation and a pointer to an Exif IFD holding the pixel
     * dimensions, which core's stored_file::rotate_image() insists on.
     *
     * @param int $width The width.
     * @param int $height The height.
     * @param int $orientation The EXIF orientation, 6 for "rotate 90 degrees clockwise to view".
     * @return string The bytes.
     */
    private static function exif_jpeg(int $width, int $height, int $orientation): string {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 20, 20));
        imagefilledrectangle($image, 0, 0, intdiv($width, 2), $height, imagecolorallocate($image, 255, 255, 255));
        ob_start();
        imagejpeg($image, null, 90);
        imagedestroy($image);
        $jpeg = (string) ob_get_clean();

        $ifd0 = pack('v', 2)
            . pack('vvVv', 0x0112, 3, 1, $orientation) . pack('v', 0)
            . pack('vvVV', 0x8769, 4, 1, 38)
            . pack('V', 0);
        $exififd = pack('v', 2)
            . pack('vvVV', 0xA002, 4, 1, $width)
            . pack('vvVV', 0xA003, 4, 1, $height)
            . pack('V', 0);
        $tiff = 'II' . pack('v', 42) . pack('V', 8) . $ifd0 . $exififd;
        $app1 = "Exif\0\0" . $tiff;
        return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1 . substr($jpeg, 2);
    }

    /**
     * Store a file in one of the course's own areas, as an editor's upload would be stored.
     *
     * @param \stdClass $course The course.
     * @param string $filearea overviewfiles or summary.
     * @param string $filename The file name.
     * @param string $content The bytes.
     * @param string $filepath The path, root by default.
     * @return \stored_file The stored file.
     */
    private static function store(
        \stdClass $course,
        string $filearea,
        string $filename,
        string $content,
        string $filepath = '/'
    ): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \core\context\course::instance($course->id)->id,
            'component' => 'course',
            'filearea' => $filearea,
            'itemid' => 0,
            'filepath' => $filepath,
            'filename' => $filename,
        ], $content);
    }

    /**
     * Drop the plugin's per-request memos: PHPUnit resets the sequences, so the ids of this test
     * repeat the previous test's, and an answer memoised there would answer here.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        \local_unlistedcourses\access::reset_caches();
        \local_unlistedcourses\category_access::reset_caches();
        \local_unlistedcourses\discoverability::reset_caches();
        \local_unlistedcourses\category_discoverability::reset_caches();
    }

    /**
     * Give a course a hotsite model, the way the course form saves one.
     *
     * @param \stdClass $course The course.
     * @return void
     */
    private static function model(\stdClass $course): void {
        \core_course\customfield\course_handler::reset_caches();
        hotsitefields::reset_field_cache();
        hotsitefields::ensure_fields();
        hotsitefields::reset_field_cache();
        \core_course\customfield\course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . hotsitefields::SHORTNAME_MODELO => hotsitefields::MODELO_CURSO,
        ]);
    }

    /**
     * A course with a hotsite model and a JPEG course image, 1600x900 by default, in the given state.
     *
     * @param int $state The discoverability state, public by default.
     * @param int $width The image width.
     * @param int $height The image height.
     * @return array The course record and the image's stored file.
     */
    private function course(int $state = discoverability::STATE_PUBLIC, int $width = 1600, int $height = 900): array {
        $course = $this->getDataGenerator()->create_course();
        $file = self::store($course, 'overviewfiles', 'capa.jpg', self::image($width, $height));
        $this->setAdminUser();
        self::model($course);
        discoverability::set_state((int) $course->id, $state);
        return [$course, $file];
    }

    /**
     * The path segments of a hashed URL: course id, content hash, file name.
     *
     * @param \stdClass $course The course.
     * @param \stored_file $file The file.
     * @param string|null $filename The last segment, the file's own name by default.
     * @return array The segments.
     */
    private static function args(\stdClass $course, \stored_file $file, ?string $filename = null): array {
        return [(string) $course->id, $file->get_contenthash(), $filename ?? $file->get_filename()];
    }

    /**
     * Store a badge on the course, the way the course edit form saves one, and return the stored file.
     *
     * Mirrors hotsitefields_test's own pattern: a customfield_data row for the badge field, made
     * through the core_customfield generator, with the picture stored under the data row's own
     * item id in customfield_picture's 'file' area.
     *
     * @param \stdClass $course The course.
     * @param string|null $filename The file name, the logo's own by default.
     * @param string|null $content The bytes, the PNG fixture by default.
     * @return \stored_file The stored badge.
     */
    private function badge(\stdClass $course, ?string $filename = null, ?string $content = null): \stored_file {
        global $CFG;

        if (!hotsitefields::picture_type_available()) {
            $this->markTestSkipped('customfield_picture is not installed or not enabled.');
        }
        $this->setAdminUser();
        hotsitefields::ensure_fields();
        /** @var \core_customfield_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $field = hotsitefields::get_field(hotsitefields::SHORTNAME_BRASAO1);
        $data = $generator->add_instance_data($field, (int) $course->id, 1);
        $record = [
            'contextid' => \core\context\course::instance($course->id)->id,
            'component' => 'customfield_picture',
            'filearea' => 'file',
            'itemid' => (int) $data->get('id'),
            'filepath' => '/',
            'filename' => $filename ?? 'brasao-alfa.png',
        ];
        if ($content !== null) {
            return get_file_storage()->create_file_from_string($record, $content);
        }
        return get_file_storage()->create_file_from_pathname($record, $CFG->dirroot . '/lib/tests/fixtures/gd-logo.png');
    }

    /**
     * The course image is the first overview file GD accepts, the one core's course image cache picks too.
     *
     * @return void
     */
    public function test_the_hero_is_the_first_valid_overview_image(): void {
        global $CFG;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->assertNull(publicfiles::hero_file($course));

        $file = self::store($course, 'overviewfiles', 'capa.jpg', self::image(400, 300));
        $this->assertSame($file->get_contenthash(), publicfiles::hero_file($course)?->get_contenthash());

        // An "image" whose bytes GD rejects is no course image, whatever its name says.
        $other = $this->getDataGenerator()->create_course();
        self::store($other, 'overviewfiles', 'capa.jpg', 'not an image');
        $this->assertNull(publicfiles::hero_file($other));

        // With room for two files, a valid image after a rejected one is found, and the route
        // and core's own course image name the same file.
        $CFG->courseoverviewfileslimit = 2;
        $second = self::store($other, 'overviewfiles', 'zz.jpg', self::image(300, 200));
        $this->assertSame($second->get_contenthash(), publicfiles::hero_file($other)?->get_contenthash());
        $core = \core_course\external\course_summary_exporter::get_course_image($other);
        $this->assertStringEndsWith('/course/overviewfiles/zz.jpg', (string) $core);
    }

    /**
     * The URLs name the theme's component, the course id and the content hash; the summary rewrite too.
     *
     * @return void
     */
    public function test_the_urls_carry_the_course_id_and_the_content_hash(): void {
        $this->resetAfterTest();
        [$course, $file] = $this->course();
        $context = \core\context\course::instance($course->id);
        $hash = $file->get_contenthash();
        $base = "/pluginfile.php/{$context->id}/theme_dimensions/";

        $hero = publicfiles::hero_url($course, $file)->out(false);
        $og = publicfiles::ogimage_url($course, $file)->out(false);
        $cover = publicfiles::cover_url($course, $file)->out(false);
        $this->assertStringEndsWith("{$base}hero/{$course->id}/{$hash}/capa.jpg", $hero);
        $this->assertStringEndsWith("{$base}ogimage/{$course->id}/{$hash}/og.jpg", $og);
        $this->assertStringEndsWith("{$base}cover/{$course->id}/{$hash}/capa.jpg", $cover);
        $this->assertNotSame($hero, $cover, 'The same file, a different area: the cover needs no model.');

        $rewritten = publicfiles::rewrite_summary('<img src="@@PLUGINFILE@@/foto.png" alt="">', $context);
        $this->assertStringContainsString("{$base}summary/{$course->id}/foto.png", $rewritten);
        $this->assertStringNotContainsString('@@PLUGINFILE@@', $rewritten);
    }

    /**
     * Only a course that is public at the moment of the request resolves, whoever minted the URL.
     *
     * @return void
     */
    public function test_resolve_serves_only_a_course_that_is_public_now(): void {
        $this->resetAfterTest();
        [$public, $publicfile] = $this->course();
        [$listed, $listedfile] = $this->course(discoverability::STATE_DEFAULT);
        [$unlisted, $unlistedfile] = $this->course(discoverability::STATE_UNLISTED);
        $this->setUser(null);

        $context = fn(\stdClass $course) => \core\context\course::instance($course->id);
        $resolved = publicfiles::resolve($context($public), 'hero', self::args($public, $publicfile));
        $this->assertSame($publicfile->get_contenthash(), $resolved?->get_contenthash());
        $this->assertNull(publicfiles::resolve($context($listed), 'hero', self::args($listed, $listedfile)));
        $this->assertNull(publicfiles::resolve($context($unlisted), 'hero', self::args($unlisted, $unlistedfile)));

        // A URL minted while the course was public stops working the moment it is not.
        $this->setAdminUser();
        discoverability::set_state((int) $public->id, discoverability::STATE_UNLISTED);
        $this->setUser(null);
        $this->assertNull(publicfiles::resolve($context($public), 'hero', self::args($public, $publicfile)));
    }

    /**
     * A public course without a hotsite model has no page for a visitor, so it has no files either.
     *
     * @return void
     */
    public function test_resolve_needs_a_hotsite_model(): void {
        $this->resetAfterTest();
        [$course, $file] = $this->course();
        $context = \core\context\course::instance($course->id);
        $this->assertNotNull(publicfiles::resolve($context, 'hero', self::args($course, $file)), 'Control: with a model.');

        $this->setAdminUser();
        \core_course\customfield\course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . hotsitefields::SHORTNAME_MODELO => 0,
        ]);
        $this->assertSame(0, hotsitefields::values($course->id)->modelo, 'Precondition: the model is gone.');
        $this->setUser(null);
        $this->assertNull(publicfiles::resolve($context, 'hero', self::args($course, $file)));
    }

    /**
     * The cover area serves a public course that has no hotsite model, which is what it exists for.
     *
     * A public category lists every public course, and most of them chose no hotsite model:
     * their cards need the image the hotsite's own areas would refuse. The control is the same
     * request on the hero and og areas, which keep the requirement they were written with, so a
     * cover exemption spelled as "no area needs a model" could not pass both halves.
     *
     * @return void
     */
    public function test_the_cover_area_serves_a_public_course_with_no_hotsite_model(): void {
        $this->resetAfterTest();
        [$course, $file] = $this->course();
        $context = \core\context\course::instance($course->id);
        $args = self::args($course, $file);

        $this->setUser(null);
        $found = publicfiles::resolve($context, publicfiles::AREA_COVER, $args);
        $this->assertSame($file->get_contenthash(), $found?->get_contenthash(), 'Control: a modelled course.');

        $this->setAdminUser();
        \core_course\customfield\course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . hotsitefields::SHORTNAME_MODELO => 0,
        ]);
        $this->assertSame(0, hotsitefields::values($course->id)->modelo, 'Precondition: the model is gone.');
        $this->setUser(null);

        $found = publicfiles::resolve($context, publicfiles::AREA_COVER, $args);
        $this->assertSame($file->get_contenthash(), $found?->get_contenthash(), 'The cover is served without a model.');
        $this->assertNull(publicfiles::resolve($context, publicfiles::AREA_HERO, $args), 'The hotsite areas still ask.');
        $ogargs = self::args($course, $file, publicfiles::OG_FILENAME);
        $this->assertNull(publicfiles::resolve($context, publicfiles::AREA_OGIMAGE, $ogargs));
    }

    /**
     * The cover refuses a course that is not public to everybody, and a URL that names other content.
     *
     * The badge area's widening - a logged-in user who may see the course - is deliberately not
     * repeated here: the logged-in listing keeps core's own image route and never mints this URL
     * ({@see \theme_dimensions\local\category\cards::images()}), so the area has no
     * reason to answer for anything but a public course. The enrolled student below pins that.
     *
     * @return void
     */
    public function test_the_cover_area_refuses_a_course_that_is_not_public_and_a_url_that_does_not_match(): void {
        $this->resetAfterTest();
        [$public, $file] = $this->course();
        [$listed, $listedfile] = $this->course(discoverability::STATE_DEFAULT);
        $context = \core\context\course::instance($public->id);
        $listedcontext = \core\context\course::instance($listed->id);
        $hash = $file->get_contenthash();
        $id = (string) $public->id;
        $student = $this->getDataGenerator()->create_and_enrol($listed, 'student');
        $this->setUser(null);

        $found = publicfiles::resolve($context, publicfiles::AREA_COVER, self::args($public, $file));
        $this->assertSame($hash, $found?->get_contenthash(), 'Control: the public course.');

        $listedargs = self::args($listed, $listedfile);
        $this->assertNull(publicfiles::resolve($listedcontext, publicfiles::AREA_COVER, $listedargs), 'Not public.');
        $this->setUser($student);
        $this->assertNull(
            publicfiles::resolve($listedcontext, publicfiles::AREA_COVER, $listedargs),
            'Not public, and no rescue for a user who may see the course: the cover fails closed.'
        );
        $this->setUser(null);

        $this->assertNull(publicfiles::resolve($context, publicfiles::AREA_COVER, [$id, sha1('stale'), 'capa.jpg']));
        $foreign = self::args($public, $file, 'outra.jpg');
        $this->assertNull(publicfiles::resolve($context, publicfiles::AREA_COVER, $foreign), 'A foreign name.');
        $this->assertNull(publicfiles::resolve($context, publicfiles::AREA_COVER, [$id, $hash]), 'Too few segments.');
        $long = [$id, $hash, 'capa.jpg', 'extra'];
        $this->assertNull(publicfiles::resolve($context, publicfiles::AREA_COVER, $long), 'Too many segments.');
    }

    /**
     * The course id in the path must be the context's own instance, and the context must be a course's.
     *
     * The summary area is where the pairing matters: it looks its file up by the context,
     * while the public check runs against the id in the path, so a mismatch would hand a
     * non-public course's file to whoever names a public course beside it. The context
     * level needs a context that is not a course's but whose instance id is a public
     * course's id - a category inserted under that id - or the instance check alone would
     * refuse it and the level check could go without anything going red.
     *
     * @return void
     */
    public function test_resolve_refuses_a_course_id_that_is_not_the_contexts_own(): void {
        global $DB;

        $this->resetAfterTest();
        [$a, $filea] = $this->course();
        [$b, $fileb] = $this->course();
        [$private] = $this->course(discoverability::STATE_DEFAULT);
        $contexta = \core\context\course::instance($a->id);
        $contextprivate = \core\context\course::instance($private->id);
        $secret = self::store($private, 'summary', 'segredo.png', self::image(11, 11, 'image/png'));
        $own = self::store($a, 'summary', 'foto.png', self::image(10, 10, 'image/png'));
        $this->setUser(null);

        // B's id and file under A's context: refused, though both courses are public.
        $this->assertNull(publicfiles::resolve($contexta, 'hero', self::args($b, $fileb)));
        // Control: A's own.
        $this->assertNotNull(publicfiles::resolve($contexta, 'hero', self::args($a, $filea)));

        // The non-public course's summary file, under its own context, beside a public course's id.
        $this->assertNull(publicfiles::resolve($contextprivate, 'summary', [(string) $a->id, 'segredo.png']));
        $this->assertNull(publicfiles::resolve($contextprivate, 'summary', [(string) $private->id, 'segredo.png']));
        $found = publicfiles::resolve($contexta, 'summary', [(string) $a->id, 'foto.png']);
        $this->assertSame($own->get_contenthash(), $found?->get_contenthash(), 'Control: a public course\'s own summary file.');
        $this->assertNotSame($secret->get_contenthash(), $found?->get_contenthash());

        // A category inserted under A's own course id, so that only the context level can refuse it.
        // insert_record() drops a given id; only insert_record_raw() honours one, as restore does.
        $DB->insert_record_raw('course_categories', [
            'id' => (int) $a->id,
            'name' => 'Twin',
            'parent' => 0,
            'depth' => 1,
            'path' => '/' . $a->id,
            'sortorder' => 0,
            'timemodified' => time(),
        ], false, false, true);
        $category = \core\context\coursecat::instance((int) $a->id);
        $this->assertSame((int) $a->id, (int) $category->instanceid, 'Precondition: a category sharing the course id.');
        $this->assertNull(publicfiles::resolve($category, 'hero', self::args($a, $filea)));

        // The site course is never public.
        $site = get_site();
        $this->assertNull(publicfiles::resolve(\core\context\course::instance(SITEID), 'hero', self::args($site, $filea)));
    }

    /**
     * A hashed area names the current content and its own file name, in exactly three segments, or nothing.
     *
     * @return void
     */
    public function test_resolve_refuses_a_stale_hash_and_a_foreign_file_name(): void {
        $this->resetAfterTest();
        [$course, $file] = $this->course();
        $context = \core\context\course::instance($course->id);
        $hash = $file->get_contenthash();
        $id = (string) $course->id;
        $this->setUser(null);

        $this->assertNull(publicfiles::resolve($context, 'hero', [$id, sha1('stale'), 'capa.jpg']));
        $this->assertNull(publicfiles::resolve($context, 'hero', self::args($course, $file, 'other.jpg')));
        $this->assertNull(publicfiles::resolve($context, 'hero', [$id, $hash]));
        $this->assertNull(publicfiles::resolve($context, 'hero', [$id, $hash, 'capa.jpg', 'extra']));
        $this->assertNull(publicfiles::resolve($context, 'ogimage', self::args($course, $file)), 'The og area wants og.jpg.');
        $this->assertNotNull(publicfiles::resolve($context, 'ogimage', self::args($course, $file, 'og.jpg')));
        $this->assertNull(publicfiles::resolve($context, 'other', self::args($course, $file)), 'Unknown area.');

        // A replaced image: the old hash is gone from the URL space, the new one resolves.
        $file->delete();
        $replacement = self::store($course, 'overviewfiles', 'capa.jpg', self::image(800, 600));
        $this->assertNull(publicfiles::resolve($context, 'hero', [$id, $hash, 'capa.jpg']));
        $resolved = publicfiles::resolve($context, 'hero', self::args($course, $replacement));
        $this->assertSame($replacement->get_contenthash(), $resolved?->get_contenthash());
    }

    /**
     * A summary file resolves by its path under the course id; a directory entry does not.
     *
     * @return void
     */
    public function test_resolve_finds_a_summary_file_by_its_path(): void {
        $this->resetAfterTest();
        [$course] = $this->course();
        $context = \core\context\course::instance($course->id);
        $root = self::store($course, 'summary', 'foto.png', self::image(10, 10, 'image/png'));
        $nested = self::store($course, 'summary', 'x.png', self::image(12, 12, 'image/png'), '/sub/');
        $this->setUser(null);
        $id = (string) $course->id;

        $found = publicfiles::resolve($context, 'summary', [$id, 'foto.png']);
        $this->assertSame($root->get_contenthash(), $found?->get_contenthash());
        $found = publicfiles::resolve($context, 'summary', [$id, 'sub', 'x.png']);
        $this->assertSame($nested->get_contenthash(), $found?->get_contenthash());
        $this->assertNull(publicfiles::resolve($context, 'summary', [$id, 'missing.png']));
        $this->assertNull(publicfiles::resolve($context, 'summary', [$id]));
        $this->assertNull(publicfiles::resolve($context, 'summary', [$id, 'sub', '.']), 'A directory entry is not a file.');
    }

    /**
     * The summary area serves images and nothing else: an inline .html would be script for every visitor.
     *
     * @return void
     */
    public function test_the_summary_area_serves_images_only(): void {
        $this->resetAfterTest();
        [$course] = $this->course();
        $context = \core\context\course::instance($course->id);
        self::store($course, 'summary', 'pagina.html', '<script>alert(1)</script>');
        self::store($course, 'summary', 'edital.pdf', '%PDF-1.4');
        self::store($course, 'summary', 'falsa.png', 'not an image');
        $image = self::store($course, 'summary', 'foto.png', self::image(10, 10, 'image/png'));
        $this->setUser(null);
        $id = (string) $course->id;

        $this->assertNull(publicfiles::resolve($context, 'summary', [$id, 'pagina.html']));
        $this->assertNull(publicfiles::resolve($context, 'summary', [$id, 'edital.pdf']));
        $this->assertNull(publicfiles::resolve($context, 'summary', [$id, 'falsa.png']));
        $found = publicfiles::resolve($context, 'summary', [$id, 'foto.png']);
        $this->assertSame($image->get_contenthash(), $found?->get_contenthash());
    }

    /**
     * The derivative is a 1200x630 JPEG, made once per content, whatever the source's shape.
     *
     * @return void
     */
    public function test_the_derivative_is_a_1200_by_630_jpeg_made_once_per_content(): void {
        $this->resetAfterTest();
        [$course, $file] = $this->course();

        $path = publicfiles::derivative($file);
        $this->assertNotNull($path);
        $this->assertFileExists($path);
        $info = getimagesize($path);
        $this->assertSame([1200, 630, 'image/jpeg'], [$info[0], $info[1], $info['mime']]);

        // Made once: a second call returns the cached file untouched.
        file_put_contents($path, 'marker');
        $this->assertSame($path, publicfiles::derivative($file));
        $this->assertSame('marker', file_get_contents($path));
        unlink($path);

        // A portrait PNG with an alpha channel is cropped to the same canvas.
        $portrait = self::store($course, 'overviewfiles', 'retrato.png', self::image(300, 900, 'image/png'));
        $info = getimagesize((string) publicfiles::derivative($portrait));
        $this->assertSame([1200, 630, 'image/jpeg'], [$info[0], $info[1], $info['mime']]);
    }

    /**
     * Pure noise, the worst case for JPEG, still encodes under the 600 KB a messaging app accepts.
     *
     * @return void
     */
    public function test_the_derivative_stays_under_the_preview_byte_ceiling(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $noise = self::store($course, 'overviewfiles', 'ruido.png', self::image(1200, 630, 'image/png', true));
        $path = publicfiles::derivative($noise);
        $this->assertNotNull($path);
        $this->assertLessThanOrEqual(publicfiles::OG_FALLBACK_MAXBYTES, filesize($path));
    }

    /**
     * A photo carrying an EXIF orientation is turned upright before it is cropped, as a browser shows it.
     *
     * The fixture is a landscape whose left half is white, tagged "rotate 90 degrees clockwise
     * to view": upright it is a portrait whose top half is white, and the centre crop of that
     * portrait is white above and dark below. Without the rotation the crop is white on the
     * left, whatever the row.
     *
     * @return void
     */
    public function test_the_derivative_turns_an_exif_rotated_photo_upright(): void {
        $this->resetAfterTest();
        $this->assertTrue(function_exists('exif_read_data'), 'Precondition: the exif extension, present on every Moodle runtime.');
        $course = $this->getDataGenerator()->create_course();
        $photo = self::store($course, 'overviewfiles', 'foto.jpg', self::exif_jpeg(1600, 900, 6));
        [$rotated] = $photo->rotate_image();
        $this->assertNotFalse($rotated, 'Precondition: core reads the orientation tag of the fixture.');

        $image = imagecreatefromjpeg((string) publicfiles::derivative($photo));
        $top = imagecolorsforindex($image, imagecolorat($image, 300, 50));
        $bottom = imagecolorsforindex($image, imagecolorat($image, 300, 600));
        imagedestroy($image);
        $this->assertGreaterThan(200, $top['red'], 'Upright: white above.');
        $this->assertLessThan(60, $bottom['red'], 'Upright: dark below.');
    }

    /**
     * The tags carry the derivative, or the raster original when it is small enough, or nothing.
     *
     * @return void
     */
    public function test_the_tags_carry_the_derivative_or_fall_back_to_a_small_original(): void {
        $this->resetAfterTest();
        [$course, $file] = $this->course(discoverability::STATE_PUBLIC, 1700, 900);
        $hash = $file->get_contenthash();

        // A 1700x900 source is 1.53 million pixels: a ceiling of one million refuses it, and the
        // tags point at the original. Checked first: a derivative once made is served whatever
        // the ceiling says, which is the point of making it once.
        $this->assertNull(publicfiles::derivative($file, 1000000));
        $image = publicfiles::ogimage($course, 1000000);
        $this->assertStringEndsWith("/hero/{$course->id}/{$hash}/capa.jpg", $image['url']);
        $this->assertSame(['image/jpeg', null, null], [$image['type'], $image['width'], $image['height']]);

        $image = publicfiles::ogimage($course);
        $this->assertStringEndsWith("/ogimage/{$course->id}/{$hash}/og.jpg", $image['url']);
        $this->assertSame(['image/jpeg', 1200, 630], [$image['type'], $image['width'], $image['height']]);

        // An original past the byte ceiling is not offered: a 600x400 noise PNG is well over 600 KB.
        $big = $this->getDataGenerator()->create_course();
        $noise = self::store($big, 'overviewfiles', 'ruido.png', self::image(600, 400, 'image/png', true));
        $this->assertGreaterThan(publicfiles::OG_FALLBACK_MAXBYTES, $noise->get_filesize(), 'Precondition: too big.');
        $this->assertNull(publicfiles::ogimage($big, 100000));

        $this->assertNull(publicfiles::ogimage($this->getDataGenerator()->create_course()), 'No image, no tag.');
    }

    /**
     * Store a share picture on the course, the way the course edit form saves one, and return the stored file.
     *
     * @param \stdClass $course The course.
     * @param string $filename The file name.
     * @param string $content The bytes.
     * @return \stored_file The stored picture.
     */
    private function share(\stdClass $course, string $filename, string $content): \stored_file {
        if (!hotsitefields::picture_type_available()) {
            $this->markTestSkipped('customfield_picture is not installed or not enabled.');
        }
        $this->setAdminUser();
        hotsitefields::ensure_fields();
        /** @var \core_customfield_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $field = hotsitefields::get_field(hotsitefields::SHORTNAME_IMAGEMPUBLICA);
        $data = $generator->add_instance_data($field, (int) $course->id, 1);
        return get_file_storage()->create_file_from_string([
            'contextid' => \core\context\course::instance($course->id)->id,
            'component' => 'customfield_picture',
            'filearea' => 'file',
            'itemid' => (int) $data->get('id'),
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
    }

    /**
     * A share picture replaces the course image under a shared link, and only there.
     *
     * The tags name its derivative; the og area serves it and stops answering the course image's
     * hash, so a scraper holding the old address meets the same refusal as a stale one; the hero
     * area - the image the page itself shows - still answers the course image. The course image
     * of the same course is the control: without a share picture it is what the tags name.
     *
     * @return void
     */
    public function test_a_share_picture_is_the_og_image_and_the_page_keeps_its_own(): void {
        $this->resetAfterTest();
        [$course, $hero] = $this->course();
        $context = \core\context\course::instance($course->id);
        $id = (string) $course->id;
        $this->assertStringEndsWith(
            "/ogimage/{$id}/{$hero->get_contenthash()}/og.jpg",
            publicfiles::ogimage($course)['url'],
            'Control: with no share picture the course image is the source.'
        );

        $share = $this->share($course, 'partilha.jpg', self::image(1400, 800));
        $this->assertNotSame($hero->get_contenthash(), $share->get_contenthash(), 'Precondition: two different pictures.');

        $image = publicfiles::ogimage($course);
        $this->assertStringEndsWith("/ogimage/{$id}/{$share->get_contenthash()}/og.jpg", $image['url']);
        $this->assertSame(['image/jpeg', 1200, 630], [$image['type'], $image['width'], $image['height']]);

        $this->setUser(null);
        $found = publicfiles::resolve($context, publicfiles::AREA_OGIMAGE, [$id, $share->get_contenthash(), 'og.jpg']);
        $this->assertSame($share->get_pathnamehash(), $found?->get_pathnamehash(), 'The og area serves what the tags named.');
        $this->assertNull(
            publicfiles::resolve($context, publicfiles::AREA_OGIMAGE, [$id, $hero->get_contenthash(), 'og.jpg']),
            'The course image is no longer the og area\'s content.'
        );
        $found = publicfiles::resolve($context, publicfiles::AREA_HERO, self::args($course, $hero));
        $this->assertSame($hero->get_pathnamehash(), $found?->get_pathnamehash(), 'The page keeps showing the course image.');
    }

    /**
     * A share picture no derivative can be made of leaves the course image as the source, not nothing.
     *
     * A vector file passes the picture field's own check (it is a web image) and GD cannot draw
     * it, so the tags and the og area both fall back to the course image, as for a course that
     * chose no share picture.
     *
     * @return void
     */
    public function test_a_share_picture_no_derivative_can_be_made_of_falls_back_to_the_course_image(): void {
        global $CFG;

        $this->resetAfterTest();
        [$course, $hero] = $this->course();
        $context = \core\context\course::instance($course->id);
        $id = (string) $course->id;
        $svg = $this->share($course, 'partilha.svg', file_get_contents(
            $CFG->dirroot . '/lib/filestorage/tests/fixtures/testimage_viewbox.svg'
        ));
        $this->assertSame('partilha.svg', hotsitefields::share_picture((int) $course->id)?->get_filename(), 'Precondition.');
        $this->assertNull(publicfiles::derivative($svg), 'Precondition: GD cannot draw a vector file.');

        $this->assertStringEndsWith("/ogimage/{$id}/{$hero->get_contenthash()}/og.jpg", publicfiles::ogimage($course)['url']);
        $this->setUser(null);
        $found = publicfiles::resolve($context, publicfiles::AREA_OGIMAGE, [$id, $hero->get_contenthash(), 'og.jpg']);
        $this->assertSame($hero->get_pathnamehash(), $found?->get_pathnamehash());
        $this->assertNull(publicfiles::resolve($context, publicfiles::AREA_OGIMAGE, [$id, $svg->get_contenthash(), 'og.jpg']));
    }

    /**
     * The badge area serves a badge of a public course to a visitor, and refuses a stale hash,
     * a foreign file name and a wrong number of path segments.
     *
     * @return void
     */
    public function test_the_badge_area_serves_a_badge_of_a_public_course_to_a_visitor(): void {
        $this->resetAfterTest();
        [$course] = $this->course();
        $badge = $this->badge($course);
        $context = \core\context\course::instance($course->id);
        $id = (string) $course->id;
        $this->setUser(null);

        $found = publicfiles::resolve($context, publicfiles::AREA_BADGE, self::args($course, $badge));
        $this->assertSame($badge->get_contenthash(), $found?->get_contenthash());

        $stale = [$id, sha1('stale'), $badge->get_filename()];
        $this->assertNull(publicfiles::resolve($context, publicfiles::AREA_BADGE, $stale), 'A stale hash is refused.');
        $foreign = self::args($course, $badge, 'other.png');
        $this->assertNull(publicfiles::resolve($context, publicfiles::AREA_BADGE, $foreign), 'A foreign name is refused.');
        $short = [$id, $badge->get_contenthash()];
        $this->assertNull(publicfiles::resolve($context, publicfiles::AREA_BADGE, $short), 'A wrong arg count is refused.');
    }

    /**
     * The badge area serves images only: a file that core does not take for an image is refused,
     * because an inline .html from it would be same-origin script for everyone who may see the page.
     *
     * The control is a real image in the same area of a second course, served through the same route.
     *
     * @return void
     */
    public function test_the_badge_area_refuses_a_file_that_is_not_an_image(): void {
        $this->resetAfterTest();
        [$course] = $this->course();
        [$control] = $this->course();
        $html = $this->badge($course, 'brasao.html', '<script>alert(1)</script>');
        $image = $this->badge($control);
        $this->setUser(null);

        $this->assertSame(
            $html->get_pathnamehash(),
            hotsitefields::badges((int) $course->id)[0]?->get_pathnamehash(),
            'Precondition: the badge field hands the route the non-image.'
        );
        $this->assertFalse($html->is_valid_image(), 'Precondition: core does not take it for an image.');

        $context = \core\context\course::instance($course->id);
        $this->assertNull(
            publicfiles::resolve($context, publicfiles::AREA_BADGE, self::args($course, $html)),
            'A non-image badge is refused.'
        );

        $found = publicfiles::resolve(
            \core\context\course::instance($control->id),
            publicfiles::AREA_BADGE,
            self::args($control, $image)
        );
        $this->assertSame($image->get_contenthash(), $found?->get_contenthash(), 'Control: an image badge is served.');
    }

    /**
     * A course that is not public serves its badges only to a logged-in user who may see it - the
     * one widening the badge area makes over the rest of the route, which the hero area never gets.
     *
     * @return void
     */
    public function test_the_badge_area_serves_a_course_that_is_not_public_to_a_logged_in_user_only(): void {
        global $DB;

        $this->resetAfterTest();
        [$course, $herofile] = $this->course(discoverability::STATE_DEFAULT);
        $badge = $this->badge($course);
        $context = \core\context\course::instance($course->id);
        $badgeargs = self::args($course, $badge);
        $heroargs = self::args($course, $herofile);

        $this->setUser(null);
        $this->assertNull(publicfiles::resolve($context, publicfiles::AREA_BADGE, $badgeargs), 'No user: refused.');

        $this->setGuestUser();
        $this->assertNull(publicfiles::resolve($context, publicfiles::AREA_BADGE, $badgeargs), 'Guest: refused.');

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $found = publicfiles::resolve($context, publicfiles::AREA_BADGE, $badgeargs);
        $this->assertSame($badge->get_contenthash(), $found?->get_contenthash(), 'Enrolled: served.');

        $bystander = $this->getDataGenerator()->create_user();
        $this->setUser($bystander);
        $found = publicfiles::resolve($context, publicfiles::AREA_BADGE, $badgeargs);
        $this->assertSame(
            $badge->get_contenthash(),
            $found?->get_contenthash(),
            'Not enrolled, but may view the course listing: served.'
        );
        // Control: the same request, the hero area - the badge rule is the only widening.
        $this->assertNull(publicfiles::resolve($context, 'hero', $heroargs));

        $DB->set_field('course', 'visible', 0, ['id' => $course->id]);
        // The public predicate memoises per request; the write above is only seen once the memo is dropped.
        discoverability::reset_caches();
        $this->assertNull(publicfiles::resolve($context, publicfiles::AREA_BADGE, $badgeargs), 'Hidden: bystander refused.');

        $this->setAdminUser();
        $found = publicfiles::resolve($context, publicfiles::AREA_BADGE, $badgeargs);
        $this->assertSame($badge->get_contenthash(), $found?->get_contenthash(), 'Hidden, but viewhiddencourses: served.');
    }

    /**
     * A course the page would ghost for a logged-in user serves that user no badge either.
     *
     * The same rule as {@see request::handle()}: a user who cannot reach an unlisted course is
     * refused its page, so the route must not hand over what the page would not show. The controls
     * are an enrolled student of the very same course and the same bystander on a course that is
     * not unlisted.
     *
     * @return void
     */
    public function test_the_badge_area_refuses_a_course_the_page_would_ghost(): void {
        $this->resetAfterTest();
        [$course] = $this->course(discoverability::STATE_UNLISTED);
        [$open] = $this->course(discoverability::STATE_DEFAULT);
        $badge = $this->badge($course);
        $openbadge = $this->badge($open);
        $context = \core\context\course::instance($course->id);
        $opencontext = \core\context\course::instance($open->id);

        $bystander = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $reset = static function (): void {
            \local_unlistedcourses\access::reset_caches();
            \local_unlistedcourses\discoverability::reset_caches();
        };

        $this->setUser($bystander);
        $reset();
        $this->assertTrue(redirector::is_ghosted((int) $course->id), 'Precondition: the page ghosts the bystander.');
        $this->assertNull(
            publicfiles::resolve($context, publicfiles::AREA_BADGE, self::args($course, $badge)),
            'A ghosted viewer is refused the badge.'
        );
        $found = publicfiles::resolve($opencontext, publicfiles::AREA_BADGE, self::args($open, $openbadge));
        $this->assertSame(
            $openbadge->get_contenthash(),
            $found?->get_contenthash(),
            'Control: the same viewer is served on a course that is not unlisted.'
        );

        $this->setUser($student);
        $reset();
        $this->assertFalse(redirector::is_ghosted((int) $course->id), 'Precondition: the page admits the student.');
        $found = publicfiles::resolve($context, publicfiles::AREA_BADGE, self::args($course, $badge));
        $this->assertSame($badge->get_contenthash(), $found?->get_contenthash(), 'Control: an enrolled student is served.');
    }

    /**
     * A public course without a hotsite model has no page for a visitor, so the badge area serves nothing either.
     *
     * @return void
     */
    public function test_the_badge_area_needs_a_hotsite_model(): void {
        $this->resetAfterTest();
        [$course] = $this->course();
        $badge = $this->badge($course);
        $context = \core\context\course::instance($course->id);
        $args = self::args($course, $badge);
        $this->assertNotNull(publicfiles::resolve($context, publicfiles::AREA_BADGE, $args), 'Control: with a model.');

        $this->setAdminUser();
        \core_course\customfield\course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . hotsitefields::SHORTNAME_MODELO => 0,
        ]);
        $this->assertSame(0, hotsitefields::values($course->id)->modelo, 'Precondition: the model is gone.');
        $this->setUser(null);
        $this->assertNull(publicfiles::resolve($context, publicfiles::AREA_BADGE, $args));
    }

    /**
     * The badge URL carries the theme's component, the course id and the content hash, like the other hashed areas.
     *
     * @return void
     */
    public function test_badge_urls_carry_the_course_id_and_the_content_hash(): void {
        $this->resetAfterTest();
        [$course] = $this->course();
        $badge = $this->badge($course);
        $context = \core\context\course::instance($course->id);
        $hash = $badge->get_contenthash();
        $base = "/pluginfile.php/{$context->id}/theme_dimensions/";

        $url = publicfiles::badge_url($course, $badge)->out(false);
        $this->assertStringEndsWith("{$base}badge/{$course->id}/{$hash}/brasao-alfa.png", $url);
    }

    /**
     * The route exists under the name file_pluginfile() dispatches to.
     *
     * @return void
     */
    public function test_the_route_is_wired_into_core_file_serving(): void {
        global $CFG;
        require_once($CFG->dirroot . '/theme/dimensions/lib.php');
        $this->assertTrue(function_exists('theme_dimensions_pluginfile'));
    }
}
