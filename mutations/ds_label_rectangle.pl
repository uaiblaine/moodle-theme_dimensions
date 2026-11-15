# Round the competency state label back into a pill.
s/(\n\.dim-comp-pill \{[^}]*?\n    border-radius: )var\(--dim-ds-radius-sm\);/${1}var(--dim-ds-radius-pill);/;
