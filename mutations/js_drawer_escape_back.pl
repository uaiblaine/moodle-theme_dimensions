# Let Escape through inside a sub-panel: it no longer goes back one level.
s/(if \(event\.key === 'Escape'\) \{\n\s+const panel = current\(root\);\n\s+)if \(panel && panel\.dataset\.parent\) \{/${1}if (false) {/;
