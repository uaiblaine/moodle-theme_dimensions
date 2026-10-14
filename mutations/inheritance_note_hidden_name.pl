# Name the source category even to a manager who may not see it.
s/\$source = \\core_course_category::get\(\$from, IGNORE_MISSING\);/\$source = \\core_course_category::get(\$from, IGNORE_MISSING, true);/;
