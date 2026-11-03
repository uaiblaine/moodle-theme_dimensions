# A ghost is read like a full card, from a course the reader may not see.
s/\$steps\[\] = \['n' => \$position, 'isghost' => true\];\n\s*continue;/\$steps[] = ['n' => \$position, 'isghost' => true];/;
