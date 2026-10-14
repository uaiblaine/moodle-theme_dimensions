# Ignore the places cap: a full instance reads as open, the one direction of error the
# classifier is not allowed.
s/if \(\$cap > 0 && \$taken >= \$cap\) \{/if (false) {/;
