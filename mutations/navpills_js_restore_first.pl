# Measure without putting the units back first: widths read off a row half emptied into More.
s/^    row\.stale = false;\n    restore\(row\);\n/    row.stale = false;\n/m;
