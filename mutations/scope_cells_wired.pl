# Draw only the cells the counts offer: the client measures a line without the cells it may need.
s/^        \$scope = \$this->scope_cells\(\$scope\);\n//m;
