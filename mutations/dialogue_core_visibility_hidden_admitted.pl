# Replace core's visibility question with the course's visible flag alone: a viewer who may view hidden courses is refused.
s/if \(!\$course \|\| !\\core_course_category::can_view_course_info\(\$course\)\) \{/if (!\$course || !\$course->visible) {/;
