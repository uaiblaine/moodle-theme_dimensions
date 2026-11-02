# Ignore the reader's count.
s/return \(int\) get_user_preferences\('calendar_maxevents', \$default\);/return (int) \$default;/;
