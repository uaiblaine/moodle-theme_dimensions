# Never mark a destination as the current page.
s/'current' => \$node->isactive && /'current' => false && /;
