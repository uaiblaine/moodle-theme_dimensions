# Put the scope's platter back at the old 6px corners, under the panel's 12px the segmented control takes.
s/(\n    \.dim-line__cells \{\n(?:        [^\n]*\n)*?        border-radius: )var\(--dim-radius\);/${1}var(--dim-radius-sm);/;
