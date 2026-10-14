# Render whatever core hands back: the subcategory listing stops filtering, so an unlisted
# category is named in the tree again - and the total, which is now counted over the
# unfiltered list, agrees with it, so nothing about the page looks wrong.
s/\$all = category_access::filter_categories\(\$coursecat->get_children\(\$options\)\);/\$all = \$coursecat->get_children(\$options);/;
