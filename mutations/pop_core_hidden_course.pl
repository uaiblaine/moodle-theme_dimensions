# Drop the course-level core gate: a hidden course is listed to a viewer who does not hold
# moodle/course:viewhiddencourses. local_unlistedcourses answers "true" for a hidden course, so
# nothing downstream catches it.
s/if \(!\$row->visible\) \{/if (false) {/;
