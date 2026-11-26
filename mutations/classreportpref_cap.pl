# A list past the cap is stored whole.
s/count\(\$seen\) >= classreport::MAX_COLUMNS/count(\$seen) >= PHP_INT_MAX/;
