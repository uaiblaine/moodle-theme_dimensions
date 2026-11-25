# Skip the login to the course: a user who is not in it is answered.
s/\n[^\n]*require_login\(\$course, false, null, false, true\);//; s/\n[^\n]*self::validate_context\(\$context\);//;
