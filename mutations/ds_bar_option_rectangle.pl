# Round a panel option back into a pill, where the Design System draws a 6px rectangle on every control.
s/(\n    \.dim-pill \{\n(?:        [^\n]*\n)*?        border-radius: )var\(--dim-radius-sm\);/${1}var(--dim-radius-pill);/;
