# Open the single area with results by itself even when results sit directly here too: the panel
# pushes down the courses the reader's search found on this very page.
s/if \(!\$isroot && \$direct === 0 && count\(\$hits\) === 1\) \{/if (!\$isroot && count(\$hits) === 1) {/;
