# Write a colour into a field without announcing the change. The quick colours and the one-click
# fixes then leave the panel, the swatch and the browser's own validation reading the previous
# value, so the page shows a grade for a pair nobody has any more.
s/^\s*input\.dispatchEvent\(new Event\('change', \{bubbles: true\}\)\);\n//m;
