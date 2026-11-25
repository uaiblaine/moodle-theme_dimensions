# Read the stored layout as it is: an unknown value reaches the block, which throws on it.
s/return get_config\('theme_dimensions', self::SETTING\) === self::VERTICAL \? self::VERTICAL : self::HORIZONTAL;/return (string) get_config('theme_dimensions', self::SETTING) ?: self::HORIZONTAL;/;
