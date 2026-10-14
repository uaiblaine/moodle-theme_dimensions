# Serve the file under any last segment: a hero URL ending in og.jpg, or anything else, then works.
s/return \(\$filename === \$expected\) \? \$file : null;/return \$file;/;
