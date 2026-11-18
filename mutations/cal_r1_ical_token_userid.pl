# Take any token with a user id.
s/\$authuserid = !empty\(\$userid\) && self::token_matches\(calendar_get_export_token\(\$user\), \$authtoken\);/\$authuserid = !empty(\$userid);/;
