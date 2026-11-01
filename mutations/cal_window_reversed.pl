# Accept a window whose last day comes before its first.
s/if \(\$last < \$first\) \{/if (false) {/;
