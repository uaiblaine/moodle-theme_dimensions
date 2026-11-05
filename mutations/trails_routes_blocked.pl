# A method that refuses the account counts as a way in.
s/if \(in_array\(\$next\['type'\], self::ROUTES, true\)\) \{/if (\$next['type'] !== access::NEXT_NONE) {/;
