# Hide an empty poster at rest as well as under a criterion: an area with no course yet disappears
# from its parent's page, and its link to its own page with it.
s/'hidden' => \$filtered && /'hidden' => /;
