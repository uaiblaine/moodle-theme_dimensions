# Read a completion row with no completion time, a course under way, as a completed course.
s/ AND course \{\$insql\} AND timecompleted > 0"/ AND course {\$insql}"/;
