# Build the root's search criteria for the logged-in shell: a visitor's root search is refused.
s/\['search' => \$query, 'anonymous' => \$anonymous\]/['search' => \$query]/;
