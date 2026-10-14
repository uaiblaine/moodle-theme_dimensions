# Hand the template the prerequisite's name already escaped: the double stash escapes it again.
s/\?: \$context,\n(\s*)'escape' => false,/?: \$context,\n$1'escape' => true,/;
