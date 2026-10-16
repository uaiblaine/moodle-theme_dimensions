# Drop the ordered insertion: a cell that comes back stays where it was created, at the end.
s/if \(node !== expected\) \{\n\s*track\.insertBefore\(node, expected\);\n\s*\}/if (false) {}/;
