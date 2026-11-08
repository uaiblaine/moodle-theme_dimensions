# The frozen flags would refuse courses while core's freezing is off and they mean nothing.
s/if \(!empty\(\$CFG->contextlocking\)\) \{/if (true) {/;
