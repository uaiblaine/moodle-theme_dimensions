# An account would be suggested the courses it is enrolled in.
s/WHERE me\.courseid = c\.id AND mue\.userid = :enroluser\)/WHERE 1 = 0 AND mue.userid = :enroluser)/;
