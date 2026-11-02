# Ignore the reader's lookahead.
s/return \(int\) get_user_preferences\('calendar_lookahead', \$default\);/return (int) \$default;/;
