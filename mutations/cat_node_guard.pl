# Drop the guard on the category NODE. The listing above still filters, so the ordinary tree
# stays correct and only a caller which hands this method a category of its own choosing -
# core's own coursecat_tree(), and anything core adds later - names what it must not.
s/(protected function coursecat_category\(coursecat_helper \$chelper, \$coursecat, \$depth\) \{\n        )if \(\$coursecat->id && !category_access::is_category_discoverable\(\(int\) \$coursecat->id\)\) \{/${1}if (false) {/;
