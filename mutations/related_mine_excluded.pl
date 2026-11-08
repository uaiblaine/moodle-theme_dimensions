# An account would be suggested the courses it is enrolled in.
s/\$rows = array_diff_key\(\$rows, array_flip\(self::mine\(array_keys\(\$rows\)\)\)\);/\$rows = \$rows;/;
