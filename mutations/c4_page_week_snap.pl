# Keep the week the address names as written, never snapped to its first day.
s/        return self::week_start\(\$week, \$firstday\);\n/        return \$week;\n/;
