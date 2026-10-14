# Accept a term of any length.
s/if \(strlen\(\$params\['query'\]\) > self::MAX_QUERY_BYTES\) \{/if (false) {/;
