# Print the total on every category page, so the hero counts the list twice: the filter bar already shows it.
s/if \(\(int\) \$this->category->id === 0\) \{\n(\s+\$stats\[\] = \[)/if (true) {\n$1/;
