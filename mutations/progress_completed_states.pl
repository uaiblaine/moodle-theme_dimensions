# Any completion record of a tracked activity reads as done, an incomplete one included.
s/\$isdone = in_array\(\(int\) \$data->completionstate, \[COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS\], true\);/\$isdone = true;/;
