# Let the groups come in the order the tree first names them, so the row changes with the plugins installed.
s/\$groups = array_fill_keys\(array_keys\(self::GROUPS\), \[\]\);/\$groups = [];/;
