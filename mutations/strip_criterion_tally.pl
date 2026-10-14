# Offer every subarea in a panel's strip under a criterion: a pill carrying the search then opens a
# subarea's page onto nothing.
s/if \(\$filtered && \(\$totals\[\$subid\] \?\? 0\) === 0\) \{/if (false) {/;
