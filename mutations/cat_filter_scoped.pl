# Admit any category at all as the tree's criterion: an id from another subtree, or one this viewer
# may not browse, then reaches the statement. Nothing LEAKS - the predicate is ANDed with the
# population's own category list rather than substituted for it, which is the design point - so what
# happens instead is that the two lists do not intersect and the page empties itself: a listing that
# was narrowed by an address nobody could have followed, with every number on it agreeing. Anchored
# on the condition, which is the guard, and never on the block around it.
s/if \(!isset\(\$scope->categories\[\$catid\]\) \|\| !\$scope->is_open\(\$catid\)\) \{/if (false) {/;
