# Accept the exit in place on a single activity course that switched focus mode on.
s/if \(!\$params\['active'\] && focusmode::leaves_course\(\$cm\)\) \{/if (false) {/;
