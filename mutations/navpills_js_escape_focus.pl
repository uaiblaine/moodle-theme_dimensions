# Escape in a menu closes it and leaves the focus on a hidden item instead of its pill.
s/close\(row, details, true\);/close(row, details, false);/;
