# The frame's height is reported as the grid's too, so the frame and the week alternate their heights.
s/if \(entry\.target === root\.current && reported > 0 && reported !== lastheight\.current\)/if (reported > 0 && reported !== lastheight.current)/;
