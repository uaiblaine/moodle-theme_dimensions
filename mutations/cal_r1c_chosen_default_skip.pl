# Hand Sass a key's default too: an untouched-looking site compiles derived triples instead of the hand-picked ones.
s/if \(\$hex !== null && \$hex !== \$default\) \{/if (\$hex !== null) {/;
