# Apply the course filters to site, user and category items too: they have no course and all go.
s/if \(!\$this->on_courses\(\) \|\| !range::course_bound\(\$item\)\) \{/if (!\$this->on_courses()) {/;
