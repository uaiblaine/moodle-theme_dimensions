# Keep a conditional card that has no prerequisite to name.
s/if \(\$state === classifier::STATE_CONDITIONAL && \$this->prerequisite === null\) \{/if (false) {/;
