# Let the focus mode service write while the master switch is off.
s/if \(!focusmode::master_enabled\(\)\) \{/if (false) {/;
