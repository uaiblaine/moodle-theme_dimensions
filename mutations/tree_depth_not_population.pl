# Let the navigation depth decide the POPULATION: the statement then reads only the categories the
# tree offers, so a course four levels down disappears from a page whose category tree was
# configured to show two - a discoverability rule quietly taken by a setting about how long a list
# of links may get, with the count line agreeing with itself the whole way down.
s/\$categories = \$scope->population_categories\(\);\n([ \t]*)if \(!\$categories\) \{\n([ \t]*)return \[\];\n([ \t]*)\}\n([ \t]*)\[\$insql, \$params\] = \$DB->get_in_or_equal\(\$categories, SQL_PARAMS_NAMED, 'cat'\);/\$categories = array_column(self::subcategory_tree(\$scope, []), 'id');\n${1}if (!\$categories) {\n${2}return [];\n${3}}\n${4}[\$insql, \$params] = \$DB->get_in_or_equal(\$categories, SQL_PARAMS_NAMED, 'cat');/;
