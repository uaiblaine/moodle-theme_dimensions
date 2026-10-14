# Keep a state chip on the request where nothing honours it: a visitor's page and the SQL cut. The
# listing is not narrowed, yet the page counts the chip as a filter - every poster wears a pill, the
# count line speaks of "these filters", the filter panel opens on no control and "Clear filters"
# clears nothing visible - and the same address draws two pages depending on the state sort.
s/if \(\$scope->anonymous \|\| !self::state_sort\(\)\) \{/if (false) {/;
