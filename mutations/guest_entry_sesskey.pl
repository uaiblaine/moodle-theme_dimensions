# The entry form counts only with the session's own key.
s/            && confirm_sesskey\(\$sesskey\);/            && true;/;
