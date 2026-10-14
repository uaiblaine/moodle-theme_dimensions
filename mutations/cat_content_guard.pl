# Drop the guard on the category CONTENTS. course/category.ajax.php enters here directly with a
# category id from the request, past the node override and past the after_config guard, which
# returns early for AJAX_SCRIPT - so the expander answers with the subtree of a category the
# page itself refuses to name.
s/(protected function coursecat_category_content\(coursecat_helper \$chelper, \$coursecat, \$depth\) \{\n        )if \(\$coursecat->id && !category_access::is_category_discoverable\(\(int\) \$coursecat->id\)\) \{/${1}if (false) {/;
