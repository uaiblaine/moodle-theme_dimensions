# Let the guest account (and a session with nobody logged in) through to the capability and the plan row.
s/if \(\$userid <= 0 \|\| \$guest\) \{/if (false) {/;
