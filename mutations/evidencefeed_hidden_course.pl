# Name a hidden course in the Filters panel to a viewer core does not show it to.
s/if \(!\$row->visible && !has_capability\('moodle\/course:viewhiddencourses', \$context\)\) \{/if (false) {/;
