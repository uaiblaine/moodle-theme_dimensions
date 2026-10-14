# Keep the hidden-category half of core's rule and drop the viewcourselist half: a category
# where the capability is prohibited for this viewer is admitted anyway.
s/if \(!\$failed && !core_course_category::can_view_category\(\$row\)\) \{/if (!\$failed && !\$row->visible && !has_capability('moodle\/category:viewhiddencategories', \\core\\context\\coursecat::instance((int) \$row->id))) {/;
