# Stop ghosting a missing id for a logged-in session: the lookup throws instead, an answer that differs from the unlisted course's.
s/if \(!\$visitor && redirector::is_address_ghosted\(\$courseid\)\) \{/if (false) {/;
#   -> test_a_logged_in_user_is_ghosted_for_a_course_that_does_not_exist (tests/route/controller/landing_test.php)
