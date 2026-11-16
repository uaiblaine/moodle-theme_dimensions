# Take the grouped order at its word: any value the address carries becomes the order of the panels,
# and a value no control can write travels into every address the page mints afterwards.
s/\$psort = in_array\(\$psort, self::PSORTS, true\) \? \$psort : '';/\$psort = \$psort;/;
