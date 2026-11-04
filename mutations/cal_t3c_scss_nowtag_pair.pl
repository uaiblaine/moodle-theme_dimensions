# Paint the Today tag in the line's lifted blue, which the dark ink does not read on.
s/(    \.dim-cal__trailnowtag \{\n(?:        [^\n]*\n)*?        )background: var\(--dim-cal-trailcurrent-tint\);/$1background: var(--dim-cal-trailcurrent-edge);/;
