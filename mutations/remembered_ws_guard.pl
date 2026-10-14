# Remember a state for any category id, withheld and nonexistent ones included.
s/\$category = guard::category\(\(int\) \$params\['categoryid'\]\);\n        \$categoryid = \(int\) \$category->id;/\$categoryid = (int) \$params['categoryid'];/;
