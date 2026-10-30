# A flavour's favicon is never shown: every look falls back to its colour.
s/if \(\$mark\['favicon'\] !== null\) \{/if (false) {/;
