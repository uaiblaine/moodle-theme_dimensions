# Offer an event course to somebody who already holds a row in it (suspended, ended, pending).
s/if \(\$mine->active \|\| \$mine->inactive \|\| \$mine->type !== access::RELATIONSHIP_NONE\) \{/if (\$mine->active) {/;
