# Ignore the view a category was left in: it opens in the last view chosen anywhere.
s/if \(\$entry !== null && isset\(\$entry\[remembered::KEY_VIEW\]\)\) \{/if (false) {/;
