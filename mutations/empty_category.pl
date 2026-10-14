# Print a card for a category holding no node: an empty "User details" heading is a
# statement that something is being withheld.
s/if \(empty\(\$facts\) && empty\(\$actions\)\) \{/if (false) {/;
