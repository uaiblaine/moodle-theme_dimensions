# Never mark the group that holds the current page.
s/'current' => in_array\(true, array_column\(\$items, 'current'\), true\),/'current' => false,/;
