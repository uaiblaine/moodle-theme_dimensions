# Refuse a tree criterion that is not below the listed category. A foreign, a deleted and a missing id
# then answer an error while a withheld descendant still answers a normal listing, which is the pair of
# answers that let a logged-in account map the category ids of a subtree it may not browse.
s/(\n        return rows::fetch\(\$category, \[)/\n        if (\$params['cat'] > 0 && !in_array((int) \$params['cat'], array_map('intval', \$category->get_all_children_ids()), true)) {\n            throw new \\invalid_parameter_exception('cat');\n        }$1/;
