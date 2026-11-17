# Draw the row in any context: a page outside a category regroups whatever secondary navigation it has.
s/if \(!\(\$page->context instanceof coursecat\)\) \{/if (false) {/;
