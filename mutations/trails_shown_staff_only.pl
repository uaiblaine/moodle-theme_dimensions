# Every account counts as staff.
s{has_capability\('moodle/course:update', \\core\\context\\course::instance\(\$courseid\), \$USER\)}{true};
