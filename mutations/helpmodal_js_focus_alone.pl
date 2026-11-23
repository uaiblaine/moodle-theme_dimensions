# Hide a long popover whatever showed it: a keyboard focus alone leaves an invisible popover on the icon.
s/if \(!trigger \|\| trigger !== activating\) \{/if (!trigger) {/;
