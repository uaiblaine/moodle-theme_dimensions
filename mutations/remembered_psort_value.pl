# Keep any value of the grouped order in an entry, not only the one A-Z is stored as.
s/if \(\(\$raw\[self::KEY_PSORT\] \?\? null\) === criteria::SORT_NAME\) \{/if ((\$raw[self::KEY_PSORT] ?? null) !== null) {/;
