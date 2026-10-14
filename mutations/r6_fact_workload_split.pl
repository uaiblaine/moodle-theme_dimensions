# Drop the online and in-person split from under a hybrid course's workload.
s/\$note = \$split === null \? null :/\$note = true ? null :/;
