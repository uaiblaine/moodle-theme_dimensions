# Decide the tree's expand-all control from the RAW child count, which is what core does. Every
# child is still withheld from the tree and the page still looks entirely normal; what comes back
# is the "Expand all" link over a tree with nothing left to expand - the same existence signal the
# per-node arrow was, one level up and printed by a different method.
s/(\$html = parent::coursecat_tree\(\$chelper, \$coursecat\);\n        if \(\$html === '' \|\| )count\(category_access::filter_categories\(\$coursecat->get_children\(\)\)\)/${1}\$coursecat->get_children_count()/;
