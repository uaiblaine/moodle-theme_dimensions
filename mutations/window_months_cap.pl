# A window of whole months takes any count past one.
s/if \(\$count < 1 \|\| \$count > self::MAX_MONTHS \|\| /if (\$count < 1 || /;
