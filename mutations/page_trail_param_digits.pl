# A trail parameter that is not a positive integer is read as one.
s/return ctype_digit\(\$trail\) && strlen\(\$trail\) <= 10 \? \(int\) \$trail : 0;/return strlen(\$trail) <= 10 ? (int) \$trail : 0;/;
