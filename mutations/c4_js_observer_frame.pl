# Measure the week's own width, which the drawer narrows, instead of the frame's.
s/const frame = root\.current\?\.parentElement \|\| null;/const frame = root.current || null;/;
