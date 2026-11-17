# Build the category navigation afresh on every page, as if each had turned its own off: a plugin's navigation is ignored.
s/if \(\$page->has_secondary_navigation\(\)\) \{/if (false) {/;
