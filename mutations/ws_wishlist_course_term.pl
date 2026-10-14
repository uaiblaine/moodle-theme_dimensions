# Drop the plugin's COURSE predicate from the write's guard. It is the whole plugin term of that
# guard - the category one is absent so that the star the listing draws on a rescued
# course can actually be pressed - so without it an unlisted course, and a course under an
# unlisted category the viewer has no relationship with, can both be starred through an address
# the page would never have rendered.
s/if \(!access::filter_courses\(\[\$courseid => \$course\]\)\) \{/if (false) {/;
