# Leave the legacy completion library to chance: the first web-service call then dies with
# "Class completion_info not found" while every unit test, whose bootstrap loads it, stays green.
s/require_once\(\$CFG->libdir \. '\/completionlib\.php'\);/\/\/ Mutated: nothing loads completionlib here./;
