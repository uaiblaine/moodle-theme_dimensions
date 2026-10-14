# Ask the plugin's predicate alone for an anonymous scope, as before the web service existed.
s/\} else if \(!publicaccess::is_public\(\$scope->rootid\)\) \{/} else if (!category_discoverability::is_public(\$scope->rootid)) {/;
