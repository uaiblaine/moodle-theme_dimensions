# Let the fallback cut a long animation that still scrolls.
s/(        quiet\.current = setTimeout\(settle, SETTLE_MS\);\n)        wait\(\);\n/$1/;
