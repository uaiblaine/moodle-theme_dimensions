# Print the "Category: name" line of a course box whatever the category. Every listing still
# drops the courses it should, so only a course kept for its own sake - one the viewer is
# enrolled in, has applied to, or administers - carries the name of a category to somebody who
# may hold no relationship with THAT.
s/if \(\$course->category && !category_access::is_category_discoverable\(\(int\) \$course->category\)\) \{/if (false) {/;
