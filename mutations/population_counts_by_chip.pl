# Count each course under its state, not under its chip.
s/\$chip = classifier::chip\(\$verdicts\[\$courseid\]\['state'\]\);/\$chip = \$verdicts[\$courseid]['state'];/;
