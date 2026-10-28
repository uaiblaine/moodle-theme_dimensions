# Read no line as drawn by its component: every painter writes into the controls React draws, which the
# next draw overwrites, and a focused control written under the reader loses its focus.
s/return Boolean\(line\) && line\.hasAttribute\(LINE_DRAWN\) && !element\.closest\(SELECTORS\.adopted\);/return false;/;
