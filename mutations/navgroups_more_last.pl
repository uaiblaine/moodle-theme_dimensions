# Put "More" first: the nodes the map does not know lead the row, ahead of "Category" and the groups.
s/\$units\[\] = (self::menu\(self::KIND_MORE, self::OTHER, \$other\));/array_unshift(\$units, $1);/;
