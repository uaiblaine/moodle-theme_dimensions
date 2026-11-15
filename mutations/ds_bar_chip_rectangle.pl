# Round a chip back into a pill, where the Design System draws a 6px rectangle.
s/(\n    \.dim-chip \{\n(?:        [^\n]*\n)*?        border-radius: )var\(--dim-radius-sm\);/${1}var(--dim-radius-pill);/;
