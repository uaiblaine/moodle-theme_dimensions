# Remove the batch cap: one call can then ask about a whole category at one completion read
# per course, and the cap is checked before login precisely so that costs nothing to refuse.
s/if \(!\$ids \|\| count\(\$ids\) > enrichment::MAX_COURSES\) \{/if (false) {/;
