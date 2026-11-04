# Let the elbow come nearer than its least over a narrow gap.
s/start \+ Math\.max\(LINK\.tight, Math\.min\(LINK\.stub, \(end - start\) \/ 2\)\)/start + Math.min(LINK.stub, (end - start) \/ 2)/;
