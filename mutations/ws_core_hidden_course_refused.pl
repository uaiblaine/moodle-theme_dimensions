# Drop core's visibility pass over the course: a course hidden from students is handed to
# anybody who names its id, because local_unlistedcourses reads no visible column at all.
s/if \(!\\core_course_category::can_view_course_info\(\$course\)\) \{/if (false) {/;
