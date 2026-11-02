# Ask the range for pieces longer than it answers.
s/\$length = min\(2 \* \$length, window::MAX_DAYS\);/\$length = 2 * \$length;/;
