# Keep a model or modality past the lines its field has.
s/if \(\$value !== false && \$value >= 1 && \$value <= \$count\) \{/if (\$value !== false && \$value >= 1) {/;
