# Stop applying the display patterns on the server: the whole value reaches the page.
s/return \$pattern === null \? \$value : identitypattern::mask\(\$value, \$pattern\);/return \$value;/;
