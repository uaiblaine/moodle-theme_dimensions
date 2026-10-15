# Remove the guard on a course that does not exist yet, so the form offers a link to
# the landing page of a course id nobody has been given.
s/if \(empty\(\$course->id\) \|\| \$course->id == SITEID\) \{/if (false) {/;
