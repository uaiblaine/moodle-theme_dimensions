# Leave the Filters panel out of the rule that closes a layer on a press outside it.
s/\[filters\.current, period\.current, organise\.current\]\.forEach\(\(layer\) => \{/[period.current, organise.current].forEach((layer) => {/;
