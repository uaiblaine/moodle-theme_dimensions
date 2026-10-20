# Stop clamping the setting to the field's options: a setting past them is written as a stored value.
s/\$lines === null \|\| \$value > count\(\$lines\)/\$lines === null/;
