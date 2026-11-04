# Keep the old position across a zoom, so the day at the start of the view moves.
s/Math\.round\(left \* dayWidth\(to\) \/ dayWidth\(from\)\)/Math.round(left)/;
