# Read every landing page field for the card, whatever the listing asked for: the other eleven fields are a
# controller each per card again, and the card's names carry fields it never prints.
s/if \(\$only !== null && !in_array\(\$shortname, \$only, true\)\) \{/if (false) {/;
