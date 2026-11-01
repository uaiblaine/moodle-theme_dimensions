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
 * Theme Dimensions - What the calendar page shows: its keys, its narrowing and its filters.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use theme_dimensions\local\landingfields;

/**
 * The keys that are off, the category, the subtree flag, the "More" filters and the colouring of the calendar page.
 *
 * One value, three spellings: the address of the page (`query()`, `from_request()`), the props of the client and
 * the stored user preference {@see PREFERENCE} (`to_array()`, `decode()`). Every way in validates against the same
 * vocabularies and DROPS what is outside them, never refuses: a key the vocabulary does not hold, a model or a
 * modality past the lines its field has, a city longer than {@see MAX_CITY}, a colouring other than the two. The
 * category is only shaped here (a positive id or 0); whether the viewer may narrow to it is the range's question
 * ({@see scope::for_viewer()}), asked by the page, which drops a refused category the same way.
 *
 * The preference is one whole, as the showcase's remembered state is ({@see \theme_dimensions\local\category\remembered}):
 * it fills a BARE address only, and an address that names any of the state's parameters is read as written, so a
 * shared link means the same to whoever opens it. The month is navigation, never state: an address naming only
 * the month is bare.
 *
 * Only a real account stores anything ({@see can_remember()}); the guest account never reaches the page.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class state {
    /** @var string The user preference holding the state, as JSON. Not declared in lib.php: the web service writes it. */
    public const PREFERENCE = 'theme_dimensions_calendar';

    /** @var string Colour an item by its key (decision D6, the default). */
    public const COLOUR_KEY = 'key';

    /** @var string Colour an item by its category's branding pair. */
    public const COLOUR_CATEGORY = 'category';

    /** @var string[] The colourings. */
    public const COLOURS = [self::COLOUR_KEY, self::COLOUR_CATEGORY];

    /** @var int The longest city kept, in characters: longer than any place name, short enough for the preference. */
    public const MAX_CITY = 100;

    /** @var string The address parameter of the month, YYYY-MM. */
    public const PARAM_MONTH = 'month';

    /** @var string The address parameter of the keys that are ON (an array; an empty member alone means none). */
    public const PARAM_KEYS = 'keys';

    /** @var string The address parameter of the category narrowing. */
    public const PARAM_CATEGORY = 'categoryid';

    /** @var string The address parameter of the subtree flag. */
    public const PARAM_RECURSIVE = 'recursive';

    /** @var string The address parameter of the models kept (an array). */
    public const PARAM_MODEL = 'model';

    /** @var string The address parameter of the modalities kept (an array). */
    public const PARAM_MODALITY = 'modality';

    /** @var string The address parameter of the city kept. */
    public const PARAM_CITY = 'city';

    /** @var string The address parameter of "only things to do". */
    public const PARAM_ACTIONONLY = 'actiononly';

    /** @var string The address parameter of the colouring. */
    public const PARAM_COLOUR = 'colour';

    /** @var string[] The parameters an address names the state by: any of them present, the address wins. */
    public const STATE_PARAMS = [
        self::PARAM_KEYS,
        self::PARAM_CATEGORY,
        self::PARAM_RECURSIVE,
        self::PARAM_MODEL,
        self::PARAM_MODALITY,
        self::PARAM_CITY,
        self::PARAM_ACTIONONLY,
        self::PARAM_COLOUR,
    ];

    /** @var string[] The keys that are off, in vocabulary order. */
    public readonly array $off;

    /** @var int The category narrowed to, 0 for none. */
    public readonly int $categoryid;

    /** @var bool Whether the category's whole subtree counts. */
    public readonly bool $recursive;

    /** @var int[] The models kept, empty for every model. */
    public readonly array $models;

    /** @var int[] The modalities kept, empty for every modality. */
    public readonly array $modalities;

    /** @var string The city kept, '' for any. */
    public readonly string $city;

    /** @var bool Whether only things to do stay. */
    public readonly bool $actiononly;

    /** @var string One of {@see COLOURS}. */
    public readonly string $colour;

    /**
     * Hold a state; every value is validated here, whichever spelling it came from.
     *
     * @param array $off The keys that are off.
     * @param int $categoryid The category, 0 for none.
     * @param bool $recursive Whether its whole subtree counts.
     * @param array $models The models kept.
     * @param array $modalities The modalities kept.
     * @param string $city The city kept.
     * @param bool $actiononly Whether only things to do stay.
     * @param string $colour The colouring.
     */
    public function __construct(
        array $off = [],
        int $categoryid = 0,
        bool $recursive = true,
        array $models = [],
        array $modalities = [],
        string $city = '',
        bool $actiononly = false,
        string $colour = self::COLOUR_KEY
    ) {
        $this->off = array_values(array_intersect(keys::ALL, array_filter($off, 'is_string')));
        $this->categoryid = max(0, $categoryid);
        $this->recursive = $recursive;
        $this->models = self::options(landingfields::SHORTNAME_MODEL, $models);
        $this->modalities = self::options(landingfields::SHORTNAME_MODALITY, $modalities);
        $city = trim(clean_param($city, PARAM_TEXT));
        $this->city = \core_text::strlen($city) > self::MAX_CITY ? '' : $city;
        $this->actiononly = $actiononly;
        $this->colour = in_array($colour, self::COLOURS, true) ? $colour : self::COLOUR_KEY;
    }

    /**
     * The values of one select field kept: integers from 1 to the field's number of lines, unique, in order.
     *
     * @param string $shortname The field.
     * @param array $values What was asked for.
     * @return int[]
     */
    private static function options(string $shortname, array $values): array {
        $lines = landingfields::option_lines($shortname);
        $count = $lines === null ? 0 : count($lines);
        $kept = [];
        foreach ($values as $value) {
            $value = filter_var($value, FILTER_VALIDATE_INT);
            if ($value !== false && $value >= 1 && $value <= $count) {
                $kept[$value] = $value;
            }
        }
        ksort($kept);
        return array_values($kept);
    }

    /**
     * The same state with another category, the one change the page makes after the range refuses a narrowing.
     *
     * @param int $categoryid The category, 0 for none.
     * @return self
     */
    public function with_category(int $categoryid): self {
        return new self(
            $this->off,
            $categoryid,
            $this->recursive,
            $this->models,
            $this->modalities,
            $this->city,
            $this->actiononly,
            $this->colour
        );
    }

    /**
     * The keys that are on, in vocabulary order.
     *
     * @return string[]
     */
    public function on(): array {
        return array_values(array_diff(keys::ALL, $this->off));
    }

    /**
     * The range's "More" filters.
     *
     * @return filters
     */
    public function filters(): filters {
        return new filters($this->models, $this->modalities, $this->city, $this->actiononly);
    }

    /**
     * Whether nothing differs from a fresh page.
     *
     * @return bool
     */
    public function is_default(): bool {
        return $this->to_array() === (new self())->to_array();
    }

    /**
     * The canonical array: the props of the client, and the stored JSON.
     *
     * @return array ['off', 'categoryid', 'recursive', 'model', 'modality', 'city', 'actiononly', 'colour'].
     */
    public function to_array(): array {
        return [
            'off' => $this->off,
            'categoryid' => $this->categoryid,
            'recursive' => $this->recursive,
            'model' => $this->models,
            'modality' => $this->modalities,
            'city' => $this->city,
            'actiononly' => $this->actiononly,
            'colour' => $this->colour,
        ];
    }

    /**
     * A state from its canonical array, every value validated; anything malformed reads as the default.
     *
     * @param array $raw The array, as stored or as sent.
     * @return self
     */
    public static function from_array(array $raw): self {
        $off = is_array($raw['off'] ?? null) ? $raw['off'] : [];
        $categoryid = filter_var($raw['categoryid'] ?? 0, FILTER_VALIDATE_INT);
        $models = is_array($raw['model'] ?? null) ? $raw['model'] : [];
        $modalities = is_array($raw['modality'] ?? null) ? $raw['modality'] : [];
        $city = is_string($raw['city'] ?? null) ? $raw['city'] : '';
        $colour = is_string($raw['colour'] ?? null) ? $raw['colour'] : self::COLOUR_KEY;
        return new self(
            $off,
            $categoryid === false ? 0 : $categoryid,
            ($raw['recursive'] ?? true) !== false,
            $models,
            $modalities,
            $city,
            ($raw['actiononly'] ?? false) === true,
            $colour
        );
    }

    /**
     * A stored value read back: the default for anything that is not a JSON object.
     *
     * @param string $json The stored value.
     * @return self
     */
    public static function decode(string $json): self {
        $raw = json_decode($json, true);
        return is_array($raw) ? self::from_array($raw) : new self();
    }

    /**
     * Whether the session's reader stores a state: a real account, never the guest, never a visitor.
     *
     * @return bool
     */
    public static function can_remember(): bool {
        return isloggedin() && !isguestuser();
    }

    /**
     * What a reader left the calendar in, validated.
     *
     * @param int|null $userid The reader, null for the session's.
     * @return self
     */
    public static function saved(?int $userid = null): self {
        if ($userid === null && !self::can_remember()) {
            return new self();
        }
        $raw = get_user_preferences(self::PREFERENCE, null, $userid);
        return $raw === null ? new self() : self::decode((string) $raw);
    }

    /**
     * Store this state as the session reader's: nothing for a reader who remembers nothing, and no row for the default.
     *
     * @return void
     */
    public function save(): void {
        if (!self::can_remember()) {
            return;
        }
        if ($this->is_default()) {
            unset_user_preference(self::PREFERENCE);
            return;
        }
        set_user_preference(self::PREFERENCE, json_encode($this->to_array(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * The state the address names, or null when it names none of {@see STATE_PARAMS}.
     *
     * The keys travel as the ones that are ON, an array, because that is what a form of checkboxes sends; the page's
     * form adds one empty member so that a submission with every box cleared still names the keys (none on). An
     * address naming the state without the keys has every key on.
     *
     * @return self|null
     */
    public static function from_request(): ?self {
        $named = false;
        foreach (self::STATE_PARAMS as $param) {
            if (isset($_GET[$param]) || isset($_POST[$param])) {
                $named = true;
                break;
            }
        }
        if (!$named) {
            return null;
        }
        $keys = self::array_param(self::PARAM_KEYS, PARAM_ALPHA);
        $off = $keys === null ? [] : array_diff(keys::ALL, $keys);
        return new self(
            $off,
            (int) optional_param(self::PARAM_CATEGORY, 0, PARAM_INT),
            (bool) optional_param(self::PARAM_RECURSIVE, 1, PARAM_BOOL),
            self::array_param(self::PARAM_MODEL, PARAM_INT) ?? [],
            self::array_param(self::PARAM_MODALITY, PARAM_INT) ?? [],
            (string) optional_param(self::PARAM_CITY, '', PARAM_TEXT),
            (bool) optional_param(self::PARAM_ACTIONONLY, 0, PARAM_BOOL),
            (string) optional_param(self::PARAM_COLOUR, self::COLOUR_KEY, PARAM_ALPHA)
        );
    }

    /**
     * What the page shows: the address when it names the state, the reader's saved state otherwise.
     *
     * @return self
     */
    public static function for_request(): self {
        return self::from_request() ?? self::saved();
    }

    /**
     * An array parameter of the request, or null when it is absent; a scalar where an array belongs reads as empty.
     *
     * optional_param_array() is not asked for a scalar: it would print a debugging message for a hand-typed address,
     * and a value outside the shape is dropped here, never reported.
     *
     * @param string $name The parameter.
     * @param string $type The PARAM_* type of each member.
     * @return array|null The cleaned members, or null when absent.
     */
    private static function array_param(string $name, string $type): ?array {
        if (!isset($_GET[$name]) && !isset($_POST[$name])) {
            return null;
        }
        $raw = $_POST[$name] ?? $_GET[$name];
        if (!is_array($raw)) {
            return [];
        }
        return optional_param_array($name, [], $type);
    }

    /**
     * The address parameters naming this state, as the page's links and its client spell them.
     *
     * Every parameter is written, so the address names the state whatever it holds; the keys are listed only when
     * one is off, with an empty member when every key is off.
     *
     * @return array name => value (arrays as lists).
     */
    public function query(): array {
        $query = [];
        if ($this->off) {
            $on = $this->on();
            $query[self::PARAM_KEYS] = $on ?: [''];
        }
        if ($this->categoryid) {
            $query[self::PARAM_CATEGORY] = $this->categoryid;
        }
        $query[self::PARAM_RECURSIVE] = $this->recursive ? 1 : 0;
        if ($this->models) {
            $query[self::PARAM_MODEL] = $this->models;
        }
        if ($this->modalities) {
            $query[self::PARAM_MODALITY] = $this->modalities;
        }
        if ($this->city !== '') {
            $query[self::PARAM_CITY] = $this->city;
        }
        if ($this->actiononly) {
            $query[self::PARAM_ACTIONONLY] = 1;
        }
        $query[self::PARAM_COLOUR] = $this->colour;
        return $query;
    }
}
