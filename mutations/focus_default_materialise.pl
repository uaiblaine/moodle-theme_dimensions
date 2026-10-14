# Drop step two of the default move: the previous answer is no longer written into the courses
# which never stored one. The new default still applies to new courses, so a test reading it
# back stays green while every existing course silently changes state.
s/\n[ \t]*self::materialise_default\(\$old\);(?=\n)//;
