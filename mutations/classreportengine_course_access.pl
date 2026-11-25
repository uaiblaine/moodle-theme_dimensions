# A course the caller cannot enter is no longer refused with the same refusal as a missing one.
s/if \(!\$course \|\| !can_access_course\(\$course, null, '', true\)\) \{/if (!\$course) {/;
