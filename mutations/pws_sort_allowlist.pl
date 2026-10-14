# Accept an organisation outside the allowlist, which criteria then drops in silence.
s/if \(!in_array\(\$params\['sort'\], criteria::SORTS, true\)\) \{/if (false) {/;
