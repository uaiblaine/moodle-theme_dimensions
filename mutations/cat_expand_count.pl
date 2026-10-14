# Decide the expand affordance from the RAW child count, which is what core does. The category
# is still named only to the entitled and its children are still withheld; what comes back is
# the live expand arrow on a node whose children are all unlisted - an arrow that opens onto
# nothing, and an existence signal every other assertion in the suite stays green over.
s/(if \(\$withcourses \|\| )count\(category_access::filter_categories\(\$coursecat->get_children\(\)\)\)/${1}\$coursecat->get_children_count()/;
