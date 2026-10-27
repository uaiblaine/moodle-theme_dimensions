# Hide the native date fields as soon as the component's node goes in, before it says it is drawn: a
# component that never draws (no site today, no strings) leaves the reader with no date control at all.
s/^(    region\.append\(node\);)$/$1\n    hideDateFields(root);/m;
