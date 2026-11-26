# A second press on the chosen option keeps it instead of releasing it.
s/state\.filters\[group\.key\] = state\.filters\[group\.key\] === id \? 0 : id;/state.filters[group.key] = id;/;
