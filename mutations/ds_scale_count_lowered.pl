# Round the phone menu's unread count into a rectangle without lowering the recorded pill count: the ratchet is exact.
s/(\.dimensions-phonemenu-count \{[^}]*?\n    border-radius: )var\(--dim-ds-radius-pill\);/${1}var(--dim-ds-radius-md);/;
