# Ask a predicate class that does not exist: every profile fatals on a site without the plugin.
s/if \(!class_exists\(\$predicate\)\) \{/if (false) {/;
