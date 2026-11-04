# A window of whole months takes no month, or a negative count.
s/if \(\$count < 1 \|\| \$count > self::MAX_MONTHS/if (\$count > self::MAX_MONTHS/;
