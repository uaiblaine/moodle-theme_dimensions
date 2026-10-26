# Never hand the chosen colour to the stylesheet: the setting saves and nothing changes.
s/if \(\$contextcolour !== null\) \{/if (false) {/;
