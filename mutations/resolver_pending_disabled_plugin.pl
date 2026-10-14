# Read applications only while enrol_apply is enabled site-wide: one lodged before it was switched off
# disappears.
s/\$appliedtime = self::pending_application\(\(int\) \$course->id\);/\$appliedtime = isset(\$plugins['apply']) ? self::pending_application((int) \$course->id) : null;/;
