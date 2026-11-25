# Stop watching the rows: a deletion leaves the band counting the row it removed.
s/\n\s*new window\.MutationObserver\(paint\)\.observe\(rows\.get\(0\), \{childList: true\}\);//;
