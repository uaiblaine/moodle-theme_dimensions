# Round the drawer's mode switch into a rectangle without lowering the recorded pill count: the ratchet is exact.
s/(\.dimensions-drawer-modes \{[^}]*?\n        border-radius: )var\(--dim-ds-radius-pill\);/${1}var(--dim-ds-radius-md);/;
