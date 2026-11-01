# Accept a key outside the vocabulary.
s/if \(!in_array\(\$name, self::ALL, true\)\) \{/if (false) {/;
