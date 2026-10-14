# Take the panel's order at its word: any value the address carries becomes the order of the panel,
# and a value no control can write travels into every address the page mints afterwards.
s/\$this->psort = \(\$this->open > 0 && in_array\(\$psort, self::PSORTS, true\)\) \? \$psort : '';/\$this->psort = (\$this->open > 0 && true) ? \$psort : '';/;
