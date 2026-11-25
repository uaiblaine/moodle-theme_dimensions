# The groups a reader may choose ignore the course's default grouping.
s/\(int\) \$course->id, 0, \(int\) \$course->defaultgroupingid, 'g\.id'/(int) \$course->id, 0, 0, 'g.id'/;
