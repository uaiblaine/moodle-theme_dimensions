# Let the toggle's press through: the submit reloads the page and throws away the history entry.
s/if \(Listing\.closestOf\(event, SELECTORS\.fav\)\) \{/if (false) {/;
