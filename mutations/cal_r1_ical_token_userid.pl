# Take any token with a user id.
s/\$authuserid = !empty\(\$userid\) && \$authtoken == calendar_get_export_token\(\$user\);/\$authuserid = !empty(\$userid);/;
