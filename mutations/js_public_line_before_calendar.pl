# Hand the line over after the calendar went in: the line put back where it stands takes the calendar
# out and in again.
s/^    Listing\.mountLine\(root, dropTerm\);\n//m;
s/^(    Listing\.mountCalendar\(root, state, setRange\)\.catch\(\(\) => null\);\n)/$1    Listing.mountLine(root, dropTerm);\n/m;
