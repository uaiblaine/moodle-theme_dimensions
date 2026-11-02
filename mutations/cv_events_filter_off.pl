# Run the text filters over a visitor's names.
s/\$options = \['context' => \$context, 'escape' => false, 'filter' => false\];/\$options = ['context' => \$context, 'escape' => false, 'filter' => true];/;
