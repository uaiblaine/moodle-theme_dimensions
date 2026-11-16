# Accept a slice with the grouped organisation, which drops the tree's criterion in silence.
s/\|\| \$sort === criteria::SORT_CATEGORY\n/|| false\n/;
