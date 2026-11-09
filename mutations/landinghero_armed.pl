# Never arm the full width banner, so the page drops its own and the header draws none.
s/            landing_hero::arm\(new landing_hero\(\$page\)\);\n//;
