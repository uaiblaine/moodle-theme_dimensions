# Make a missing predicate ghost everybody over their CATEGORY: every category page then
# dead-ends the moment the dependency is absent. The course half of this rule has its own gate
# (ghost_fail_open); this one is anchored on the category predicate so the two cannot collide.
s/(never fail a page over it\.\n            return )false;(\n        \}\n        return !\$predicate::is_category_discoverable)/${1}true;${2}/;
