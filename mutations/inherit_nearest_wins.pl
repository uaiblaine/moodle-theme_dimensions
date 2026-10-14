# Let the farthest ancestor win: a subcategory's own pair stops overriding its parent's.
s/\$depths\[\$categoryid\] >= \(int\) \$row->depth/\$depths[\$categoryid] < (int) \$row->depth/;
