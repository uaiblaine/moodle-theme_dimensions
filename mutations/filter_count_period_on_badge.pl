# Count the period on the Filters badge again, although the date button shows it.
s/(public function filter_count\(\): int \{\n        return count\(\$this->fields\))/${1} + (\$this->datefrom !== \x27\x27 || \$this->dateto !== \x27\x27 ? 1 : 0)/;
