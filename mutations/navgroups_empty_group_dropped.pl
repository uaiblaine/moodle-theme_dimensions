# Keep a group with no destination for the viewer: an empty menu on the row.
s/if \(\$items === \[\]\) \{/if (false) {/;
