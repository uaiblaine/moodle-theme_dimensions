# The Trilhas view is off on a site that never stored the setting.
s/get_config\('theme_dimensions', self::TRAILS_SETTING\) !== '0';/get_config('theme_dimensions', self::TRAILS_SETTING) === '1';/;
