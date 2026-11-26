# A facet in force is counted under its own condition, so every other option reads 0.
s/self::count_options\(\$table, \$identifier, \[\$identifier => \$filter\]\)/self::count_options(\$table, '', [\$identifier => \$filter])/;
