# Copy any MUC entry: Boost Union's unstamped compile, or one from before a theme build, is served as this revision's.
s/if \(!is_string\(\$css\) \|\| !str_starts_with\(\$css, self::stamp\(\$rev, \$subrev\)\)\)/if (!is_string(\$css))/;
