# Rely on whoever loaded the completion library before: a web service request may not have, and the card dies
# with "Class completion_info not found" in the browser while every test process had the library loaded.
s/\n[^\n]*require_once\(\$CFG->libdir \. '\/completionlib\.php'\);//;
