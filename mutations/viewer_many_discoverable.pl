# Keep a course local_unlistedcourses withholds from the reader.
s/if \(\$courseid == SITEID \|\| empty\(\$discoverable\[\$courseid\]\)\) \{/if (\$courseid == SITEID) {/;
