# Re-admit a core-hidden course on an active enrolment in the PHP cut: core never lists it to a
# viewer without moodle/course:viewhiddencourses, enrolled or not (theme #75).
s/if \(!\$row->visible\) \{/if (!\$row->visible && !\$active) {/;
