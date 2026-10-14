# Let the site course through the course guard. Every gate below waves it past on its own -
# can_view_course_info() returns true outright for SITEID and its category is 0, which holds no
# row - so this refusal is the only thing that stops the showcase's one write being aimed at the
# front page.
#
# It reddens ONE test, and only that one: guard_test::test_the_site_course_is_refused_where_the
# _plugin_admits_everything, which is the only test on this suite that runs with nothing unlisted.
# Everywhere else local_unlistedcourses has already been made to evaluate, and it then fails
# closed on the pseudo-category and refuses the site course before this line is reached.
s/if \(\$courseid == SITEID\) \{/if (false) {/;
