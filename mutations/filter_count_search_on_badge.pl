# Count the search on the Filters badge, although the term is in its own box and not in the panel.
s/(public function filter_count\(\): int \{\n        \$count = count\(\$this->fields\))/${1} + (\$this->search !== '' ? 1 : 0)/;
