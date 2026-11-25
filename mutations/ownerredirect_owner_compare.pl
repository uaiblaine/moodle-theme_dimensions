# Redirect whoever names an existing plan: the row is read, but its owner is no longer compared with the session user.
s/if \(\(int\) \$owner !== \$userid\) \{/if (\$owner === false) {/;
