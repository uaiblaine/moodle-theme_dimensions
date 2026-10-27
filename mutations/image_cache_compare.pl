# Write every course's entry into core's course_image cache on every page again, right or not.
s/if \(!array_key_exists\(\$courseid, \$stored\) \|\| \$stored\[\$courseid\] !== \$value\) \{/if (true) {/;
