# Mount no calendar on the visitor's page: the two native date fields stay, and the period has no picker.
s/^    Listing\.mountCalendar\(root, state, setRange\)\.catch\(\(\) => null\);\n//m;
