# Round the card's state label back into a pill.
s/(\n    \.dim-badge \{[^}]*?\n        border-radius: )var\(--dim-ds-radius-sm\);/${1}var(--dim-radius-pill);/;
