# Core's preference services write the look, past choose() and the look cohorts.
s/(function preference_editable\(.*?\): bool \{\s+)return false;/$1return true;/s;
