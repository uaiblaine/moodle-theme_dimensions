# Stop at the top level: Assign roles and Check permissions, which core nests under Permissions, vanish.
s/yield from self::walk\(\$child\);/\/\/ Mutated: the children are not walked./;
