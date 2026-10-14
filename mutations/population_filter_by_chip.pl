# Keep a course by its state, not by its chip.
s/return classifier::chip\(\$verdicts\[\$courseid\]\['state'\]\) === \$criteria->state;/return \$verdicts[\$courseid]['state'] === \$criteria->state;/;
