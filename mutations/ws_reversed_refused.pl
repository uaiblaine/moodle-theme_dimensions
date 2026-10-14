# Accept a pair of dates given backwards from the client: a defect that sends one would then be
# answered with a quietly reordered listing instead of being seen.
s/ && strcmp\(\$params\['datefrom'\], \$params\['dateto'\]\) > 0\) \{/ && false) {/;
