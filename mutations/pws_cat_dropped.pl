# Refuse a tree criterion that is not below the listed category, as the logged-in function did before it stopped.
s/return rows::fetch\(\$category, \$args\);/if (\$args['cat'] > 0 && !in_array(\$args['cat'], array_map('intval', \$category->get_all_children_ids()), true)) {\n            throw new \\invalid_parameter_exception('cat');\n        }\n        return rows::fetch(\$category, \$args);/;
