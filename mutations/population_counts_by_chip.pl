# Count each course under its state, not under its chip.
s/\$counts\[classifier::chip\(\$verdicts\[\$courseid\]\['state'\]\)\]\+\+;/\$counts[\$verdicts[\$courseid]['state']]++;/;
