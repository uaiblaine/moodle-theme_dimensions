# Let guest access behind a key walk straight into the course, as free access does.
s/if \(\$state === classifier::STATE_ENROLLED \|\| \$state === classifier::STATE_FREE\) \{/if (\$state === classifier::STATE_ENROLLED || \$state === classifier::STATE_FREE || \$state === classifier::STATE_KEY) {/;
