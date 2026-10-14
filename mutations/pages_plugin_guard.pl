# Call the catalogue without asking whether the class exists: a site without local_page then dies
# on every category page instead of drawing it without the strip.
s/if \(!class_exists\(\$catalogue\) \|\| !method_exists\(\$catalogue, 'for_viewer'\)\) \{/if (false) {/;
