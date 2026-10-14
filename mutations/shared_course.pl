# Remove the shared-course relationship: fellow participants lose each other.
s/if \(self::shares_a_course_with_viewer\(\$user\)\) \{/if (false) {/;
