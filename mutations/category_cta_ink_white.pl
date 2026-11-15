# Pin the Filters badge's ink back to white: a light brand colour (a yellow, a light green) then puts white text on
# its own fill, under 4.5:1, while the default blue still looks right.
s/(\.dim-toolbar__filtercount \{[^}]*?color: )var\(--dim-cta-ink\)/$1#fff/s;
