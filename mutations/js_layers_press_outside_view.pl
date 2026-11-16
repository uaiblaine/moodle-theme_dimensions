# Leave the View menu out of the rule that closes a layer on a press outside it.
s/\[filters\.current, period\.current, organise\.current, viewmenu\.current\]\.forEach\(\(layer\) => \{/[filters.current, period.current, organise.current].forEach((layer) => {/;
