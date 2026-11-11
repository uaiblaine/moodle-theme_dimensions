# Pin the checked chip's ink back to white: a light brand colour (a yellow, a light green) then puts white text on
# its own fill, under 4.5:1, while the default blue still looks right.
s/(\.dim-pill__input:checked \+ \.dim-pill \{[^}]*?color: )var\(--dim-cta-ink\)/$1#fff/s;
