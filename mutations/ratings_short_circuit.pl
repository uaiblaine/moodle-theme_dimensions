# Read the summary table on a site where tool_courserating rates no course: the statement every page pays for nothing.
s/if \(!\$courseids \|\| !self::ratings_enabled\(\)\) \{/if (!\$courseids) {/;
