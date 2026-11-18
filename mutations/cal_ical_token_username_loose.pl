# Put the old address's check back to core's ==.
s/self::token_matches\(sha1\(\$username \. \$user->password \. \$CFG->calendar_exportsalt\), \$authtoken\)/\$authtoken == sha1(\$username . \$user->password . \$CFG->calendar_exportsalt)/;
