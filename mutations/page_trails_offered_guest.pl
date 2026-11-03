# The guest account is offered the Trilhas view.
s/return isloggedin\(\) && !isguestuser\(\) && trail::available\(\);/return isloggedin() \&\& trail::available();/;
