# Take any token with a username.
s/\$authusername = !empty\(\$username\)\n\s+&& self::token_matches\(sha1\(\$username \. \$user->password \. \$CFG->calendar_exportsalt\), \$authtoken\);/\$authusername = !empty(\$username);/;
