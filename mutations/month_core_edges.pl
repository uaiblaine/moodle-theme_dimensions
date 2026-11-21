# The grid's days outside the month are not fetched: their dots vanish.
s/foreach \(\$edges as \$edge\) \{/foreach ([] as \$edge) {/;
