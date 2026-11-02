# Drop the plugin's course term: a course local_unlistedcourses withholds is answered.
s/if \(!access::filter_courses\(\[\$courseid => \$course\]\)\) \{/if (false) {/;
