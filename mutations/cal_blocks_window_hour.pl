# Start the agenda at the current second, not at the current hour as core does.
s/        \$hour = \$hour->setTime\(\(int\) \$hour->format\('G'\), 0\);\n//;
