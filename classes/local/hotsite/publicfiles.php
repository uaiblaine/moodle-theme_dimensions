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
 * Theme Dimensions - Files of the public hotsite
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hotsite;

use theme_dimensions\local\hotsitefields;

/**
 * The file route of the public hotsite: the course image, the summary's files and the Open Graph image.
 *
 * Under forcelogin, core's file_pluginfile() answers course/overviewfiles and course/summary
 * with require_login(), so a visitor of a public page gets 303 for the course image and for
 * every image embedded in the summary, and a scraper finds no og:image to fetch. This route
 * serves the same stored files - it copies nothing - under the theme's own component, which
 * file_pluginfile() hands to theme_dimensions_pluginfile() with no login check of
 * its own. Every check is here, in {@see resolve()}: the course must be public NOW (the page
 * that minted the URL is not trusted), the course id in the path must be the context's own
 * instance (so a URL cannot pair one course's context with another's id), and an area keyed by
 * content must name the current content.
 *
 * Five areas, each carrying the course id as its item id:
 *  - hero/<courseid>/<contenthash>/<filename>: the first overview image, bytes as stored;
 *  - cover/<courseid>/<contenthash>/<filename>: the same file again, for a public page that is
 *    NOT a hotsite - the card of the public category listing. The one area a course reaches
 *    WITHOUT a hotsite model, because a public category lists every public course and most of
 *    them chose no model: the hero and og areas belong to the hotsite, and the page they belong
 *    to exists only with a model, so their requirement stays exactly as it was;
 *  - ogimage/<courseid>/<contenthash>/og.jpg: a 1200x630 JPEG derivative made once per content
 *    under localcachedir - of the share picture when the course has one a derivative can be
 *    made of, else of the course image ({@see og_source()});
 *  - summary/<courseid>/<filepath><filename>: a file embedded in the course summary;
 *  - badge/<courseid>/<contenthash>/<filename>: one of the course's badge pictures, bytes as
 *    stored. The one area that also serves a course that is NOT public, to a logged-in user
 *    who may see the course: the badge fields are custom fields visible to nobody, so the
 *    picture type's own route refuses them to everyone and the hotsite has no other way to
 *    show them. The rule mirrors the logged-in guards of {@see request::handle()}. The cover
 *    area gets no such rescue: a course that is not public is refused there whoever asks, and
 *    the logged-in listing never mints that URL - it keeps core's own.
 *
 * The content hash in the hero and og paths is the cache-buster: social networks keep a
 * scrape from a day to indefinitely, so a stable name would never update a preview after
 * the image was replaced. Every answer is sent cacheability private:
 * send_stored_file() defaults to public for an anonymous requester, and a shared proxy would
 * keep serving an unpublished course's image for the whole max-age with nothing at the Moodle
 * layer able to clear it. A refusal is a bare 404, never send_file_not_found(), which throws
 * and renders a themed error page through core_renderer::header() for an anonymous user.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class publicfiles {
    /** @var string The component the URLs carry: file_pluginfile() dispatches on it. */
    public const COMPONENT = 'theme_dimensions';

    /** @var string The course image, bytes as stored. */
    public const AREA_HERO = 'hero';

    /** @var string The course image again, for a public page without a hotsite: the same bytes. */
    public const AREA_COVER = 'cover';

    /** @var string The Open Graph derivative of the course image. */
    public const AREA_OGIMAGE = 'ogimage';

    /** @var string A file embedded in the course summary. */
    public const AREA_SUMMARY = 'summary';

    /**
     * @var string A badge picture: a file of the customfield_picture area of one of the badge fields.
     *
     * customfield_picture is optional: without it {@see hotsitefields::badges()} answers none and
     * every address of this area is the same 404 as a stale hash.
     */
    public const AREA_BADGE = 'badge';

    /** @var int Width of the Open Graph derivative. */
    public const OG_WIDTH = 1200;

    /** @var int Height of the Open Graph derivative. */
    public const OG_HEIGHT = 630;

    /** @var string File name of the Open Graph derivative, the last segment of its URL. */
    public const OG_FILENAME = 'og.jpg';

    /** @var string Media type of the Open Graph derivative. */
    public const OG_TYPE = 'image/jpeg';

    /**
     * @var int JPEG quality of the derivative.
     *
     * WhatsApp drops a preview image past roughly 600 KB. At 82 a 1200x630 of pure noise -
     * the worst case for JPEG - encodes to about 519 KB, so the ceiling holds for every source.
     */
    public const OG_QUALITY = 82;

    /**
     * @var int Bytes above which the raster original is not offered as og:image.
     *
     * The original stands in only when no derivative can be made; a scraper then fetches it
     * as it is, so it must fit the same ceiling the derivative exists to enforce.
     */
    public const OG_FALLBACK_MAXBYTES = 614400;

    /**
     * @var int Source pixels above which no derivative is attempted.
     *
     * The derivative is made inside a page render, under raise_memory_limit(MEMORY_EXTRA):
     * 384 MB on 64-bit. At 25 million pixels, decoding a PNG, rotating it and covering the
     * canvas peaks about 200 MB over the request's baseline. The ceiling leaves room for the
     * raw bytes and the page, and still admits a 24-megapixel camera photo.
     */
    public const MAX_SOURCE_PIXELS = 25000000;

    /** @var int Lifetime of an answer whose URL carries the content hash. */
    private const HASHED_LIFETIME = 7 * DAYSECS;

    /** @var int Lifetime of a summary file, whose URL carries no hash: core's own figure. */
    private const PLAIN_LIFETIME = HOURSECS;

    /** @var string[] Raster types a scraper uses as they are, when no derivative can be made. */
    private const RASTER_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /**
     * The course image: its first overview file that GD accepts, picked the way core's course image cache picks it.
     *
     * @param \stdClass $course The course record.
     * @return \stored_file|null The file, or null when the course has no usable image.
     */
    public static function hero_file(\stdClass $course): ?\stored_file {
        $element = new \core_course_list_element($course);
        foreach ($element->get_course_overviewfiles() as $file) {
            if ($file->is_valid_image()) {
                return $file;
            }
        }
        return null;
    }

    /**
     * The URL of the course image through this route.
     *
     * @param \stdClass $course The course record.
     * @param \stored_file $file The course image ({@see hero_file()}).
     * @return \core\url The URL, carrying the course id and the content hash.
     */
    public static function hero_url(\stdClass $course, \stored_file $file): \core\url {
        return self::hashed_url($course, $file, self::AREA_HERO, $file->get_filename());
    }

    /**
     * The URL of the course image for a card of a public page that is not a hotsite.
     *
     * The same file and the same bytes as {@see hero_url()}, under the area whose contract does
     * not ask for a hotsite model.
     *
     * @param \stdClass $course The course record.
     * @param \stored_file $file The course image ({@see hero_file()}).
     * @return \core\url The URL, carrying the course id and the content hash.
     */
    public static function cover_url(\stdClass $course, \stored_file $file): \core\url {
        return self::hashed_url($course, $file, self::AREA_COVER, $file->get_filename());
    }

    /**
     * The URL of a badge picture through this route.
     *
     * @param \stdClass $course The course record.
     * @param \stored_file $file The badge ({@see hotsitefields::values()} crests).
     * @return \core\url The URL, carrying the course id and the content hash.
     */
    public static function badge_url(\stdClass $course, \stored_file $file): \core\url {
        return self::hashed_url($course, $file, self::AREA_BADGE, $file->get_filename());
    }

    /**
     * The alternative text of a badge: its file name without the extension, read as words.
     *
     * Hyphens and underscores become spaces and runs of whitespace collapse, so
     * "crest-alpha.png" is announced as "crest alpha" rather than as a slug. The editor
     * names the file; the field's description and the category editor's help both say so.
     *
     * One spelling for every badge of this theme: the hotsite's grid, the course card of the
     * category showcase and a category's own crests on its hero and its directory card all
     * call this, so the same file cannot be announced two ways on two pages. It takes the file
     * NAME rather than a stored_file because the category's cached branding payload holds the
     * name and never the file ({@see \theme_dimensions\local\category\branding}).
     *
     * @param string $filename The badge's file name.
     * @return string The plain spelling.
     */
    public static function badge_alt(string $filename): string {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        return trim((string) preg_replace('/\s+/u', ' ', str_replace(['-', '_'], ' ', $name)));
    }

    /**
     * The URL of the Open Graph derivative of the course image.
     *
     * @param \stdClass $course The course record.
     * @param \stored_file $file The course image ({@see hero_file()}).
     * @return \core\url The URL, carrying the course id and the content hash.
     */
    public static function ogimage_url(\stdClass $course, \stored_file $file): \core\url {
        return self::hashed_url($course, $file, self::AREA_OGIMAGE, self::OG_FILENAME);
    }

    /**
     * A URL of this route whose path carries the file's content hash.
     *
     * @param \stdClass $course The course record.
     * @param \stored_file $file The file the URL is about.
     * @param string $filearea The area.
     * @param string $filename The last segment.
     * @return \core\url The URL.
     */
    private static function hashed_url(\stdClass $course, \stored_file $file, string $filearea, string $filename): \core\url {
        return \core\url::make_pluginfile_url(
            $file->get_contextid(),
            self::COMPONENT,
            $filearea,
            (int) $course->id,
            '/' . $file->get_contenthash() . '/',
            $filename
        );
    }

    /**
     * Point the summary's embedded files at this route instead of core's course/summary area.
     *
     * @param string $text The summary as stored, with @@PLUGINFILE@@ placeholders.
     * @param \core\context\course $context The course context.
     * @return string The summary with the placeholders rewritten.
     */
    public static function rewrite_summary(string $text, \core\context\course $context): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        return file_rewrite_pluginfile_urls(
            $text,
            'pluginfile.php',
            $context->id,
            self::COMPONENT,
            self::AREA_SUMMARY,
            (int) $context->instanceid
        );
    }

    /**
     * The Open Graph image of a course, if it has one.
     *
     * The derivative when it can be made - which happens here, once per content, so that the
     * tags only claim a size the route will serve - else the original when it is a raster a
     * scraper can use and no bigger than the derivative is allowed to be, else nothing: an
     * image tag pointing at nothing usable is worse than none. Its source is
     * {@see og_source()}: the share picture an editor chose, else the course image. Only the
     * course image can reach the fallback to the original, because the share picture is the
     * source only when its derivative was made, and the original is served by the hero area.
     *
     * @param \stdClass $course The course record.
     * @param int $maxpixels Source pixels above which no derivative is attempted.
     * @return array|null Keys url, type, width and height (the last two null without a derivative), or null.
     */
    public static function ogimage(\stdClass $course, int $maxpixels = self::MAX_SOURCE_PIXELS): ?array {
        $file = self::og_source($course, $maxpixels);
        if ($file === null) {
            return null;
        }
        if (self::derivative($file, $maxpixels) !== null) {
            return [
                'url' => self::ogimage_url($course, $file)->out(false),
                'type' => self::OG_TYPE,
                'width' => self::OG_WIDTH,
                'height' => self::OG_HEIGHT,
            ];
        }
        if (in_array($file->get_mimetype(), self::RASTER_TYPES, true) && $file->get_filesize() <= self::OG_FALLBACK_MAXBYTES) {
            return [
                'url' => self::hero_url($course, $file)->out(false),
                'type' => $file->get_mimetype(),
                'width' => null,
                'height' => null,
            ];
        }
        return null;
    }

    /**
     * The picture the Open Graph image of a course is made from: the share picture when it can be one, else the course image.
     *
     * The share picture is the hotsite's own field for exactly this (the pair of the public
     * description): a course may show one picture on its page and want another under a shared
     * link. It is the source only when its 1200x630 derivative can be made - a vector file, a
     * picture past the pixel ceiling or one GD cannot read falls back to the course image, as a
     * course that chose no share picture always has, rather than to no image at all. The og area
     * of {@see resolve()} asks this same question, so the route serves exactly the source the
     * tags named and a URL carrying the course image's hash stops answering once a share
     * picture takes over. Without customfield_picture there is no share picture, and the course
     * image is the source.
     *
     * @param \stdClass $course The course record.
     * @param int $maxpixels Source pixels above which no derivative is attempted.
     * @return \stored_file|null The source, or null when the course has neither.
     */
    public static function og_source(\stdClass $course, int $maxpixels = self::MAX_SOURCE_PIXELS): ?\stored_file {
        $share = hotsitefields::share_picture((int) $course->id);
        if ($share !== null && self::derivative($share, $maxpixels) !== null) {
            return $share;
        }
        return self::hero_file($course);
    }

    /**
     * The 1200x630 JPEG derivative of an image, made once per content and kept under localcachedir.
     *
     * The image is scaled to cover the canvas and cropped to it from the centre, so the size
     * the tags promise is the size served; a small source is enlarged rather than padded. A
     * JPEG carrying an EXIF orientation is turned upright first, the way a browser shows it,
     * through core's own stored_file::rotate_image(). The file is keyed by the content hash
     * and the canvas and quality it was made with, so it needs no invalidation: a replaced
     * image has a new hash, a changed constant a new name, and a cache purge only costs one
     * rebuild per image.
     *
     * @param \stored_file $file The source image.
     * @param int $maxpixels Source pixels above which no derivative is attempted.
     * @return string|null The path of the derivative, or null when it cannot be made.
     */
    public static function derivative(\stored_file $file, int $maxpixels = self::MAX_SOURCE_PIXELS): ?string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $directory = make_localcache_directory(self::COMPONENT . '/' . self::AREA_OGIMAGE);
        $path = $directory . '/' . $file->get_contenthash() . '-' . self::OG_WIDTH . 'x' . self::OG_HEIGHT
            . '-q' . self::OG_QUALITY . '.jpg';
        if (file_exists($path)) {
            return $path;
        }
        $info = $file->get_imageinfo();
        if (!$info || empty($info['width']) || empty($info['height'])) {
            return null;
        }
        if ((int) $info['width'] * (int) $info['height'] > $maxpixels) {
            return null;
        }
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            return null;
        }
        raise_memory_limit(MEMORY_EXTRA);
        $width = (int) $info['width'];
        $height = (int) $info['height'];
        [$source, $size] = $file->rotate_image();
        if ($source) {
            // Upright now; the dimensions are the rotated ones.
            $width = (int) $size['width'];
            $height = (int) $size['height'];
        } else {
            $source = @imagecreatefromstring($file->get_content());
        }
        if (!$source) {
            return null;
        }
        $data = self::cover($source, $width, $height);
        imagedestroy($source);
        if ($data === null) {
            return null;
        }
        file_safe_save_content($data, $path);
        return file_exists($path) ? $path : null;
    }

    /**
     * Scale an image to cover the Open Graph canvas, crop it from the centre and encode it as JPEG.
     *
     * @param \GdImage $source The decoded source image.
     * @param int $width Its width.
     * @param int $height Its height.
     * @return string|null The JPEG bytes, or null when GD failed.
     */
    private static function cover(\GdImage $source, int $width, int $height): ?string {
        $scale = max(self::OG_WIDTH / $width, self::OG_HEIGHT / $height);
        $sourcewidth = min($width, (int) round(self::OG_WIDTH / $scale));
        $sourceheight = min($height, (int) round(self::OG_HEIGHT / $scale));
        $sourcex = (int) floor(($width - $sourcewidth) / 2);
        $sourcey = (int) floor(($height - $sourceheight) / 2);

        $target = imagecreatetruecolor(self::OG_WIDTH, self::OG_HEIGHT);
        if (!$target) {
            return null;
        }
        // A transparent source lands on white: JPEG has no alpha channel.
        imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            $sourcex,
            $sourcey,
            self::OG_WIDTH,
            self::OG_HEIGHT,
            $sourcewidth,
            $sourceheight
        );
        ob_start();
        $encoded = imagejpeg($target, null, self::OG_QUALITY);
        $data = (string) ob_get_clean();
        imagedestroy($target);
        return ($encoded && $data !== '') ? $data : null;
    }

    /**
     * Resolve a request to the stored file it names, or refuse it.
     *
     * Nothing about the request is trusted: the course must be public at this moment and -
     * on every area but the cover - have a hotsite model, the two conditions under which
     * {@see request::handle()} serves a visitor, so those areas are reachable exactly when the
     * page is.
     * The cover area answers for the public category listing, which lists every public
     * course whether or not it chose a model, so the model requirement would refuse most of
     * the covers it exists to serve; the public predicate is what guards it. The course id
     * in the path must be the context's own instance (the summary area looks its file up by
     * the context, so a mismatch there would pair one course's id with another's files), a
     * hashed area must name the current content and its own file name, and the summary and
     * badge areas serve images only: a page for the open internet embeds pictures, and an inline
     * .html from either area would be same-origin script for every visitor. The site course and bad
     * ids are refused by {@see publicaccess::is_public()}, which never holds them public.
     *
     * @param \core\context $context The context the URL names.
     * @param string $filearea The area.
     * @param array $args The path segments after the area: the course id first.
     * @return \stored_file|null The file to send, or null to refuse.
     */
    public static function resolve(\core\context $context, string $filearea, array $args): ?\stored_file {
        if ($context->contextlevel != CONTEXT_COURSE) {
            return null;
        }
        $courseid = (int) array_shift($args);
        if ($courseid !== (int) $context->instanceid) {
            return null;
        }
        if (!publicaccess::is_public($courseid) && !($filearea === self::AREA_BADGE && self::viewer_may_see($courseid))) {
            return null;
        }
        if ($filearea !== self::AREA_COVER && !hotsitefields::model($courseid)) {
            return null;
        }
        switch ($filearea) {
            case self::AREA_BADGE:
                if (count($args) !== 2) {
                    return null;
                }
                [$hash, $filename] = $args;
                foreach (hotsitefields::badges($courseid) as $badge) {
                    if ($badge->get_contenthash() === $hash && $badge->get_filename() === $filename) {
                        return $badge->is_valid_image() ? $badge : null;
                    }
                }
                return null;
            case self::AREA_HERO:
            case self::AREA_COVER:
            case self::AREA_OGIMAGE:
                if (count($args) !== 2) {
                    return null;
                }
                [$hash, $filename] = $args;
                $course = get_course($courseid);
                // The og area serves what the tags named, which may be the share picture.
                $file = ($filearea === self::AREA_OGIMAGE) ? self::og_source($course) : self::hero_file($course);
                if ($file === null || $file->get_contenthash() !== $hash) {
                    return null;
                }
                $expected = ($filearea === self::AREA_OGIMAGE) ? self::OG_FILENAME : $file->get_filename();
                return ($filename === $expected) ? $file : null;
            case self::AREA_SUMMARY:
                $filename = array_pop($args);
                $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
                $file = get_file_storage()->get_file($context->id, 'course', 'summary', 0, $filepath, $filename);
                // A directory entry has no mimetype, so the image check refuses it too.
                return ($file && $file->is_valid_image()) ? $file : null;
        }
        return null;
    }

    /**
     * The courses among these whose badges the route would serve a logged-in reader: {@see viewer_may_see()} for many.
     *
     * The same four tests, in the same order, read in bulk: logged in and not the guest account; a
     * course local_unlistedcourses keeps discoverable by its own state (the course term
     * {@see redirector::is_ghosted()} asks, one batch for them all); visible, or hidden with the
     * capability to see it; and a view of its category's listing or an active enrolment. Keep the
     * two in step: course_badges_test holds this answer against what {@see resolve()} really serves.
     * A visitor is answered nothing here, although the route serves a public course's badge to
     * anybody: the callback that asks is for a logged-in reader's cards, and nothing is the safe side.
     *
     * @param \stdClass[] $courses Course rows carrying id, category and visible, their contexts preloaded.
     * @return int[] The ids of the courses kept, in the order given.
     */
    public static function viewer_may_see_many(array $courses): array {
        global $USER;

        if (!$courses || !isloggedin() || isguestuser()) {
            return [];
        }
        $ids = [];
        foreach ($courses as $course) {
            $ids[] = (int) $course->id;
        }
        $discoverable = \local_unlistedcourses\access::are_courses_discoverable($ids);
        $kept = [];
        foreach ($courses as $course) {
            $courseid = (int) $course->id;
            if ($courseid == SITEID || empty($discoverable[$courseid])) {
                continue;
            }
            $context = \core\context\course::instance($courseid);
            if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context)) {
                continue;
            }
            if (\core_course_category::can_view_course_info($course) || is_enrolled($context, $USER, '', true)) {
                $kept[] = $courseid;
            }
        }
        return $kept;
    }

    /**
     * Whether the current user, logged in, may see a course that is not public - the hotsite page's rule.
     *
     * Nobody who is not logged in, and no guest: a visitor of a course that is not public
     * is refused everything, on the page and here. A logged-in user needs a course the
     * page would not ghost ({@see redirector::is_ghosted()}), visible (or the capability to see
     * it hidden), and either a view of its category's listing or an active enrolment, as
     * {@see request::handle()} checks before rendering. {@see viewer_may_see_many()} is the same rule
     * read in bulk; keep the two in step.
     *
     * @param int $courseid The course id.
     * @return bool Whether to serve.
     */
    private static function viewer_may_see(int $courseid): bool {
        global $USER;

        if (!isloggedin() || isguestuser() || $courseid == SITEID) {
            return false;
        }
        $course = get_course($courseid);
        $context = \core\context\course::instance($courseid);
        if (redirector::is_ghosted($courseid)) {
            return false;
        }
        if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context)) {
            return false;
        }
        return \core_course_category::can_view_course_info($course) || is_enrolled($context, $USER, '', true);
    }

    /**
     * Send the file a request names, or answer 404. Does not return.
     *
     * The og area sends the derivative or nothing: its URL and the tags that carry it
     * promise a 1200x630 JPEG, and the original under that name would be a different
     * file, type and size from what was advertised. The original has its own area. The
     * cover needs no branch: its URL carries the content hash, so it takes the hashed
     * lifetime with the hero, and cacheability private with everything here.
     *
     * @param \core\context $context The context the URL names.
     * @param string $filearea The area.
     * @param array $args The path segments after the area.
     * @return void
     */
    public static function serve(\core\context $context, string $filearea, array $args): void {
        $file = self::resolve($context, $filearea, $args);
        if ($file === null) {
            self::refuse();
        }
        \core\session\manager::write_close();
        $options = ['cacheability' => 'private'];
        if ($filearea === self::AREA_OGIMAGE) {
            $path = self::derivative($file);
            if ($path === null) {
                self::refuse();
            }
            send_file($path, self::OG_FILENAME, self::HASHED_LIFETIME, 0, false, false, self::OG_TYPE, false, $options);
        }
        $lifetime = ($filearea === self::AREA_SUMMARY) ? self::PLAIN_LIFETIME : self::HASHED_LIFETIME;
        send_stored_file($file, $lifetime, 0, false, $options);
    }

    /**
     * Refuse with a bare 404 and stop.
     *
     * Not send_file_not_found(): that throws, and the handler renders a full themed error
     * page through core_renderer::header() for an anonymous user - the render this whole
     * design avoids, and one that answered a missing file with a 500 rather than a 404.
     *
     * @return void
     */
    private static function refuse(): void {
        send_header_404();
        die;
    }
}
