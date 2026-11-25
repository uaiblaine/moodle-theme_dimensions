# An activity the viewer cannot see still gives its competencies as columns.
s/if \(\$cm === null \|\| !\$cm->uservisible\) \{/if (false) {/;
