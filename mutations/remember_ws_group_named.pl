# Read the sort through the grouping's vocabulary even when no grouping is named: an unknown sort resets the entry.
s/if \(in_array\(\$params\['group'\], criteria::GROUPS, true\)\) \{/if (true) {/;
