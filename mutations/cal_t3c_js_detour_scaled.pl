# Keep the detour's turn at the day zoom's room, before the first day held on a narrow day.
s/const turn = end - Math\.max\(LINK\.tight, Math\.min\(LINK\.detour, \(start - end\) \/ 2, end - LINK\.line \/ 2\)\);/const turn = end - LINK.detour;/;
