# Draw the now line outside the hour window.
s/if \(column < 0 \|\| minutes < window\.from \* 60 \|\| minutes >= window\.to \* 60\) \{/if (column < 0) {/;
