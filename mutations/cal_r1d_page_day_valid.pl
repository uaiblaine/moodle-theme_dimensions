# A malformed day view's day is kept as written.
s/if \(!self::is_day\(\$day\) \|\| \(int\) \$day < 1900 \|\| \(int\) \$day > 2999\) \{/if (\$day === '') {/;
