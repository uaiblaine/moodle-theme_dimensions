# transparent, currentColor and inherit draw an invisible dot instead of none.
s/if \(in_array\(strtolower\(\$colour\), \['transparent', 'currentcolor', 'inherit'\], true\)\) \{/if (false) {/;
