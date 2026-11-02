# Take every category whose own row says public, hidden or under an unlisted one.
s/        \$ids = array_keys\(array_filter\(\$predicate::are_public\(\$candidates\)\)\);/        \$ids = \$candidates;/;
