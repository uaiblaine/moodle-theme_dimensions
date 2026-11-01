# Leave core's key bitmask in $SESSION: the read-only range would write the session.
s/            unset\(\$SESSION->calendarshoweventtype\);\n//;
