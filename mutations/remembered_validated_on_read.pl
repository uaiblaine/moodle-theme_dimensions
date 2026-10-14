# Hand a stored entry on as it is stored, hand-edited and stale values included.
s/\$entry = self::entry\(\$value, \$options\);/\$entry = \$value;/;
