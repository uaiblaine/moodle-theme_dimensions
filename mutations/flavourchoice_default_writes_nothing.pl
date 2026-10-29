# The site default writes too, hiding every category and cohort flavour behind no flavour.
s/if \(\$entry->record === null\) \{/if (false) {/;
