# Drop core's visibility question: a course core hides from this reader is answered whenever the plugin has nothing to say.
s/if \(!\$course \|\| !\\core_course_category::can_view_course_info\(\$course\)\) \{/if (!\$course) {/;
