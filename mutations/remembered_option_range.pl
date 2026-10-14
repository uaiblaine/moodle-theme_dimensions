# Accept any positive option of a field filter, one the field does not have included.
s/if \(\$value >= 1 && \$value <= \$options\[\$fieldid\]\) \{/if (\$value >= 1) {/;
