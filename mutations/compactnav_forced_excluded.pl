# Count the items forced into "More" as candidates: they are in "More" at every width.
s/ \|\| !empty\(\$item\['forceintomoremenu'\]\)\) \{/) {/;
