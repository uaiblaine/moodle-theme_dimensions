# The guest account is offered the Trilhas view.
s/return isloggedin\(\) && !isguestuser\(\) && self::trails_enabled\(\) && trail::available\(\);/return isloggedin() \&\& self::trails_enabled() \&\& trail::available();/;
