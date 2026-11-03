# The last open step is next, not the first.
s/if \(\$next === null && \(\$state === self::CURRENT/if ((\$state === self::CURRENT/;
