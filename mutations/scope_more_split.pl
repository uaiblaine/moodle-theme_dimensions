# Put every state value on the line: "More statuses" is then empty and its values become scope cells.
s/if \(criteria::is_scope\(\(string\) \$option\['value'\]\)\) \{/if (true) {/;
