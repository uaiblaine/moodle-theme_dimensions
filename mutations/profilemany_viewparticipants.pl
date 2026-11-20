# Stop asking for viewparticipants: a course that withholds its list no longer withholds profiles.
s/\$viewable\[\$courseid\] = \$coursecontext\n\s*&& has_capability\('moodle\/course:viewparticipants', \$coursecontext\);/\$viewable[\$courseid] = (bool) \$coursecontext;/
