# Carry the page address's own query into the block's links.
s/    url\.search = '';\n    url\.searchParams\.set\('month', month\);/    url.searchParams.set('month', month);/;
