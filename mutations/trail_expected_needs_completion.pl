# An expected date left behind on a trail that no longer tracks completion closes the step, though core dropped its event.
s/\$expected = \$tracked\[\$courseid\] \? \(int\) \$cm->completionexpected : 0;/\$expected = (int) \$cm->completionexpected;/;
