# Accept a sheet without the theme's rules: a failed SCSS compile's fallback is stored and served.
s/if \(!str_contains\(\$css, self::MARKER\)\)/if (false)/;
