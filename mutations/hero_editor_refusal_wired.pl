# Load the category on the page with core's lookup and stop asking the form's refusal: unknowncategory and
# nopermissions come back as two answers.
s/\$coursecat = categoryhero_form::editable_category\(\$id\);/\$coursecat = core_course_category::get(\$id, MUST_EXIST, true);/;
