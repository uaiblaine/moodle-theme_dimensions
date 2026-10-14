# Accept a negative page, which criteria then reads as the first.
s/if \(\$params\['page'\] < 0\) \{/if (false) {/;
