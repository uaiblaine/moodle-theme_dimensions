# Pad the capsule off its named 3px: the bar no longer 40px.
s/(\n    \.dim-line__capsule \{\n(?:        [^\n]*\n)*?        )padding: \.1875rem;/${1}padding: .25rem;/;
