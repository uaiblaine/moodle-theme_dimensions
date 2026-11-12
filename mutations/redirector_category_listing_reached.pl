# Drop the category half of core's guard: a course in a category the reader may not list then reaches core's error.
s/return !\\core_course_category::can_view_course_info\(\$course\) && !is_enrolled\(\$context, \$USER, '', true\);/return false;/;
