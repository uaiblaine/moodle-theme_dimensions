# Hand the visitor's shell the logged-in calendar component: its page would load a module the owner
# kept off the visitor's import graph until stage R4.
s/Listing\.mountCalendar\(root, state, setRange\)\.catch/Listing.mountCalendar(root, state, setRange, '\@moodle\/lms\/theme_dimensions\/daterange\/DateRange').catch/;
