# Drop the light partner: a dark file is used on a site whose navbar shows the site name, not a logo.
s/if \(empty\(get_config\('theme_boost_union', self::LIGHT\[\$variant\]\)\)\) \{/if (false) {/;
