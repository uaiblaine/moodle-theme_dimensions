# Re-admit a core-hidden course on an active enrolment in the SQL cut (rescued_hidden), the twin
# of the PHP cut gate (theme #75).
s/if \(has_capability\('moodle\/course:viewhiddencourses', self::course_context\(\$row\)\)\) \{/if ((\$scope->enrolments[(int) \$courseid]->active ?? false) || has_capability('moodle\/course:viewhiddencourses', self::course_context(\$row))) {/;
