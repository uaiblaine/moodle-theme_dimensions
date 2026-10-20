# Write at None too: every new course gets an empty row for each field instead of no row.
s/if \(\$value === self::NONE\) \{/if (false) {/;
