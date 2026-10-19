# Draw the course index control where core draws no course index: a control that opens nothing.
s/return course_get_format\(\$page->course\)->uses_course_index\(\);/return true;/;
