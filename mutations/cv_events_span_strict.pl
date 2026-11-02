# Keep an event that ends at the window's first instant.
s/e\.timestart \+ e\.timeduration > :spanstart/e.timestart + e.timeduration >= :spanstart/;
