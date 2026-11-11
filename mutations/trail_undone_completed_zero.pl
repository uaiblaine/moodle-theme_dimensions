# An undone step answers 0: drop the done test from the completion time.
s/\$completed = \$isdone \? \(int\) \$data->timemodified : 0;/\$completed = (int) \$data->timemodified;/;
