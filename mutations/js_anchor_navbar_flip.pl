# Judge whether the popover can go above its opener against the navbar's offset plus the gap, not the gap alone.
# Above would then start under a fixed navbar whenever below does not fit, and the clamp drags it down over the opener.
s/above >= least \|\|/above >= GAP ||/;
