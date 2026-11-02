# Ask the predicate about the site and ids that are not positive.
s/            if \(\$courseid > 0 && \$courseid != SITEID\) \{\n                \$ids\[\$courseid\] = \$courseid;/            if (true) {\n                \$ids[\$courseid] = \$courseid;/;
