# Answer an event whose row names a group.
s/WHERE e\.groupid = 0 AND e\.courseid = 0 AND/WHERE e.courseid = 0 AND/;
