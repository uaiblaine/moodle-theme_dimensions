# Take the posters' component out of the one place it is named: no shell mounts it, and the walk no
# longer reaches what the visitor's page loads.
s/    areas: '\@moodle\/lms\/theme_dimensions\/areas\/Areas',\n//;
