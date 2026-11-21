# A bare id again: get_fast_modinfo() reads each course row itself.
s/get_fast_modinfo\(\$records\[\$courseid\] \?\? \$courseid, \$userid\)/get_fast_modinfo(\$courseid, \$userid)/;
