# Ask core for the category WITH alwaysreturnhidden: can_view_category() is skipped, so a
# category core hides from this viewer is served by the web service. The plugin's predicate is
# no substitute - it answers true wholesale for a hidden category.
s/core_course_category::get\(\$categoryid, MUST_EXIST\)/core_course_category::get(\$categoryid, MUST_EXIST, true)/;
