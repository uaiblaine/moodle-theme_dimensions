# Take any week the address names, malformed or impossible.
s/if \(!self::is_day\(\$week\) \|\| \(int\) \$week < 1900 \|\| \(int\) \$week > 2999\) \{/if (\$week === '') {/;
