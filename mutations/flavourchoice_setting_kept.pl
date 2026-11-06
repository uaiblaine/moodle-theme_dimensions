# ensure_setting() writes whatever is stored: a site's chosen looks would be wiped on upgrade.
s/if \(get_config\('theme_dimensions', self::SETTING\) === false\) \{/if (true) {/;
