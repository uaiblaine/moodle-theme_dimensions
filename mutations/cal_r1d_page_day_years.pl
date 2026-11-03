# The day view's day outside the month's years is kept.
s/if \(!self::is_day\(\$day\) \|\| \(int\) \$day < 1900 \|\| \(int\) \$day > 2999\) \{/if (!self::is_day(\$day)) {/;
