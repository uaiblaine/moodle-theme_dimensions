# Admit a slice that names the grouping by area: the visitor's panel would be the whole grouped page.
s/            \|\| \(string\) \(\$args\['group'\] \?\? ''\) === criteria::GROUP_CATEGORY;/            || false;/;
