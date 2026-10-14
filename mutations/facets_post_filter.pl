# Count a group over every course that carries a value, not over the post-filter list: a
# withheld course moves a facet number by one.
s/\$remaining = self::apply\(\$values, \$courseids, \$others\);/\$remaining = array_keys(\$values[\$fieldid] ?? []);/;
