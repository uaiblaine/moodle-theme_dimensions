# Take any token with a username.
s/\$authusername = !empty\(\$username\) && \$authtoken == sha1\(\$username \. \$user->password \. \$CFG->calendar_exportsalt\);/\$authusername = !empty(\$username);/;
