# Read any stored value but the column as full width, so a value outside the vocabulary moves the banner.
s/return get_config\('theme_dimensions', self::SETTING\) === self::FULLWIDTH;/return get_config('theme_dimensions', self::SETTING) !== self::COLUMN;/;
