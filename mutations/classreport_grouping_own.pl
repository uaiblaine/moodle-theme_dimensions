# A separate-groups grader's own groups ignore the course's default grouping.
s/\(int\) \$USER->id, \(int\) \$course->defaultgroupingid, 'g\.id'/(int) \$USER->id, 0, 'g.id'/;
