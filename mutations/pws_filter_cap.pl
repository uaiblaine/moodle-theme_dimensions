# Accept any number of filters.
s/if \(count\(\$params\['filters'\]\) > facets::MAX_FIELDS\) \{/if (false) {/;
