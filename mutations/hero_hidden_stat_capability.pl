# Print the hidden-course count to anybody the population admitted a hidden course to: a student
# enrolled in one then reads a staff number on their own hero.
s/if \(\$this->result->hidden > 0 && has_capability\('moodle\/course:viewhiddencourses', \$context\)\) \{/if (\$this->result->hidden > 0) {/;
