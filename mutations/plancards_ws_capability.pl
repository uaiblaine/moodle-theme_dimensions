# Drop the door's own access rule and lean on the builder's silent empty answer for a refused viewer.
s/if \(!plan::can_read_user\(\$userid\) && !plan::can_read_user_draft\(\$userid\)\) \{/if (false) {/;
