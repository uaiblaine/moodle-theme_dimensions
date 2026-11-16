# Answer a grouping outside the vocabulary instead of refusing it.
s/if \(\$group !== '' && !in_array\(\$group, criteria::GROUPS, true\)\) \{/if (false) {/;
