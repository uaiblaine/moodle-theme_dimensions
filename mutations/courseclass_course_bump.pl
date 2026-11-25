# Leave the course's figures stale after a rating, an evidence, an enrolment or a reset.
s/(            if \(\(int\) \$event->courseid > 0\) \{\n)                self::bump\(self::course_key\(\(int\) \$event->courseid\)\);\n/$1/;
