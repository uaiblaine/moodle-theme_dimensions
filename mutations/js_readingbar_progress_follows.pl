# Leave the course's progress as the server printed it: the sub line says "0 of 1" after the activity is marked done.
s/        if \(progress\) \{\n            countProgress\(progress, detail\.completed \? 1 : -1\)\.catch\(Notification\.exception\);\n        \}\n//;
