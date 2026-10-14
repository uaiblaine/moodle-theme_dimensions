# Only enabled instances of an enabled enrol_guest offer the entry.
s/enrol_get_instances\(\(int\) \$course->id, true\)/enrol_get_instances((int) \$course->id, false)/;
