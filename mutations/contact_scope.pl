# Stop scoping the course contact exception to the course in the URL: the exception
# becomes global again, which is precisely the core behaviour this class narrows.
s/if \(\$course !== null && self::is_course_contact_of\(\$user, \$course\)\) \{/if (has_coursecontact_role(\$user->id)) {/;
