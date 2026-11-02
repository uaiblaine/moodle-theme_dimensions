# Drag an item core does not let the viewer drag.
s/if \(!entry \|\| !edit \|\| !edit\.drag\) \{/if (!entry || !edit) {/;
