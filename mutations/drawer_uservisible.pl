# Link the helper's item on its say-so: an activity the reader may not see now is printed and linked.
s/if \(\$cm === null \|\| !\$cm->uservisible \|\| \$cm->url === null\) \{/if (\$cm === null || \$cm->url === null) {/;
