# Hide the open area when an answer zeroes it. The panel the reader is looking at then loses its
# poster from the grid - its row, its arrow and its control - instead of staying until it is closed
# (the open-stays rule).
s/itemOf\(poster\)\.hidden = filtered && count === 0 && id !== state\.open;/itemOf(poster).hidden = filtered && count === 0;/;
