# Arm the whole listing on a root without a search line: Back would fetch for a page that has no control to show it.
s/const live = !\(Listing\.isRoot\(state\) && !root\.querySelector\(SELECTORS\.toolbar\)\);/const live = true;/;
