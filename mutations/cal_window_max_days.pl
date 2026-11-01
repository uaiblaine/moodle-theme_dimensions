# Let a window hold any number of days: one call would read a year of events.
s/if \(\$count > self::MAX_DAYS\) \{/if (false) {/;
