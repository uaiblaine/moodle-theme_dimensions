# The card continues at the last step, not the next.
s/if \(\$progress\['next'\] === \$step->cmid\) \{/if (true) {/;
