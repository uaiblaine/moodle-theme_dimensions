# The Trilhas view is offered without mod_subcourse.
s/return isloggedin\(\) && !isguestuser\(\) && self::trails_enabled\(\) && trail::available\(\);/return isloggedin() \&\& !isguestuser() \&\& self::trails_enabled();/;
