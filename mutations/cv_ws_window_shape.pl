# Leave a malformed window to the later read.
s/            window::from_iso\(\$params\['from'\], \$params\['to'\], new \\DateTimeZone\('UTC'\)\);/            \$unused = 0;/;
