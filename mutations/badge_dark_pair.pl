# Drop the dark ink of the "open" state badge: the dark block then declares a background with
# no colour beside it, the light value survives into dark mode, and "Inscricoes abertas" is
# near-black green text on a near-black green fill. Nothing in the pipeline reads a colour pair.
s/^\s*--dim-badge-open-fg: \#a9ee55;.*\n//m;
