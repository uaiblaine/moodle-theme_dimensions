# Tell the component the scope in the answer's order rather than SCOPE's.
s/\.filter\(\(option\) => rank\(option\) >= 0\)\.sort\(\(a, b\) => rank\(a\) - rank\(b\)\)/.filter((option) => rank(option) >= 0)/;
