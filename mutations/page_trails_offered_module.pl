# The Trilhas view is offered without mod_subcourse.
s/return isloggedin\(\) && !isguestuser\(\) && trail::available\(\);/return isloggedin() \&\& !isguestuser();/;
