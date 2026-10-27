# Share the FIRST course's controller of each field with every course, its stored row included: every course
# then reads the first one's values.
s/\$controllers\[\$fieldid\] = \$data\[\$courseid\]\[\$fieldid\]\n(\s*)\?\? \(\$defaults\[\$fieldid\] \?\?= (data_controller::create\(0, \(object\) \[.instanceid. => \$courseid\], \$field\))\);/\$controllers[\$fieldid] = (\$defaults[\$fieldid] ??= (\$data[\$courseid][\$fieldid] ?? $2));/;
