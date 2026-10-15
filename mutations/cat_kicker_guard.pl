# Name the category in the landing page's hero kicker whoever is looking. The page itself is still
# withheld from anybody with no relationship to the COURSE, so what leaks is narrower and
# quieter: a learner enrolled in one course reads the name of a category they may not discover.
s/if \(\$loggedin && !category_access::is_category_discoverable\(\(int\) \$this->course->category\)\) \{/if (false) {/;
