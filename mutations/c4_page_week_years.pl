# Take a real day outside the years a month may name.
s/if \(!self::is_day\(\$week\) \|\| \(int\) \$week < 1900 \|\| \(int\) \$week > 2999\) \{/if (!self::is_day(\$week)) {/;
