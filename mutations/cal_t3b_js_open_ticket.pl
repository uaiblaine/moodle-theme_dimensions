# Open a course's popover for an action the reader has since replaced.
s/if \(ticket === asked\.current\) \{\n(\s*)arrived\(/if (true) {\n$1arrived(/;
