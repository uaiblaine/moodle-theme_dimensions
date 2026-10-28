# Hand the line over after the calendar's node went into it: the line put back where it stands carries
# a calendar already mounting, which core's observer unmounts and mounts again.
s/^    mountLine\(\);\n//m;
s/^(    mountCalendar\(\)\.catch\(Notification\.exception\);\n)/$1    mountLine();\n/m;
