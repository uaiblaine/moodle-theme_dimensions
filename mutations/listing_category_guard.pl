# Drop the plugin's category gate from the page: a category local_unlistedcourses withholds is handed
# to the population, which refuses it with the theme's own exception instead of falling back to core's page.
s/if \(!category_access::filter_categories\(\[\$coursecat\]\)\) \{/if (false) {/;
