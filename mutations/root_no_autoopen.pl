# Let the root name an area to open by itself when one poster holds every match: the root opens no
# area on its own.
s/if \(!\$isroot && \$direct === 0 && count\(\$hits\) === 1\) \{/if (\$direct === 0 && count(\$hits) === 1) {/;
