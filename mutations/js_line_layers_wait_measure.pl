# Place the layers and read the rows' fades in the first commit: offsetParent and scrollWidth force the layout there.
s/if \(!line \|\| !drawn\.current \|\| !st\.measuredOnce\) \{/if (!line || !drawn.current) {/;
