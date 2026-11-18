# Put the user id check back to core's ==: the helper stays strict and nothing calls it there.
s/self::token_matches\(calendar_get_export_token\(\$user\), \$authtoken\)/\$authtoken == calendar_get_export_token(\$user)/;
