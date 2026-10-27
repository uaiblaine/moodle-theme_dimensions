# The same in the SQL cut's capped probe: the plugin reads one {context} row per probed course again.
s/        foreach \(\$probe as \$row\) \{\n\s*context_helper::preload_from_record\(clone \$row\);\n\s*\}\n(\s*\$admitted = )/$1/;
