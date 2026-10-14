# Let moodle/course:view alone open a course HIDDEN from students, by dropping the precondition
# core asks first. can_access_course() refuses a hidden course to anybody without
# moodle/course:viewhiddencourses BEFORE it ever reaches is_viewing(), so without this line the
# card hands a viewer a link into a course the course itself then refuses - and every other
# assertion about the card stays true, because the badge, the muting and the panel do not change.
s/        if \(empty\(\$this->row->visible\) && !has_capability\('moodle\/course:viewhiddencourses', \$context\)\) \{\n            return false;\n        \}\n//;
