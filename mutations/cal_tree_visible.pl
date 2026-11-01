# Name a category core hides from the viewer.
s/            \$category = \\core_course_category::get\(\$categoryid, IGNORE_MISSING\);/            \$category = \\core_course_category::get(\$categoryid, IGNORE_MISSING, true);/;
