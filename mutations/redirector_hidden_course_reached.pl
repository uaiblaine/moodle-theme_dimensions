# Let a course core hides through the ghost: a hidden course the reader may not see then reaches core's
# "unavailable" error instead of the ghost page a missing id gets.
s/if \(!\$course->visible && !has_capability\('moodle\/course:viewhiddencourses', \$context\)\) \{\n(\s*)return true;/if (false) {\n$1return true;/;
