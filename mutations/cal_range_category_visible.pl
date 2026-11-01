# Name a category core hides from the viewer.
s/\\core_course_category::get\(\$categoryid, IGNORE_MISSING\)/\\core_course_category::get(\$categoryid, IGNORE_MISSING, true)/;
