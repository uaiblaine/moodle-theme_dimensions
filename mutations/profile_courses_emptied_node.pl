# Print a list with no course left: an empty "Course profiles" heading says something is being withheld.
s/if \(!\$replacement\[.courses.\]\) \{/if (false) {/;
