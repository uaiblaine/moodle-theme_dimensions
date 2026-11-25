# Leave the course's figures stale after a role or a capability changes in the course.
s/(            if \(\$level === CONTEXT_COURSE && \(int\) \$event->courseid > 0\) \{\n)                self::bump\(self::course_key\(\(int\) \$event->courseid\)\);\n/$1/;
