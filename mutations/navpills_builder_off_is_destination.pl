# Draw the row on every page that turned the navigation off, a destination of the row or not (D99).
s/if \(!in_array\(true, array_column\(\$units, 'current'\), true\)\) \{/if (false) {/;
