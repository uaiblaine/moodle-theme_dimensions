# Count the facet options over a population no filter of this request ever narrowed: the numbers
# beside the pills then describe the category rather than the page, and a reader picking an option
# that says nine is shown two. The date bound is the one this gate is named for, and it is the one
# a test asserts, but the mutation drops the search and the field filters from the count with it.
s/\$ids = facets::apply\(\$values, array_keys\(\$kept\), \$criteria->fields\);/\$ids = facets::apply(\$values, array_keys(self::gate(\$scope, self::records(\$scope, new criteria(\$scope->rootid)))), \$criteria->fields);/;
