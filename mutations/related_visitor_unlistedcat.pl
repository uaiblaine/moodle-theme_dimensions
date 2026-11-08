# A visitor's ranking would hold the courses under an unlisted category, which the gate refuses.
s/\$closed \.= " OR wcat\.id \{\$unlistedsql\}";/\$closed .= " OR 1 = 0";/;
