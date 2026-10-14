# Ask the plugin's predicate alone where the whole public chain is asked, as population::scope() did.
s/if \(!publicaccess::is_public\(\$categoryid\)\) \{/if (!\\local_unlistedcourses\\category_discoverability::is_public(\$categoryid)) {/;
