# Copy a moved group's links into More instead of moving them: two links per destination.
s/section\.append\(\.\.\.unit\.items\);/section.append(...unit.items.map((link) => link.cloneNode(true)));/;
