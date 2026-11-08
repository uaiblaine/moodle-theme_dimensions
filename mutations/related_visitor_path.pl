# A visitor's ranking would read only the course's own category, never a closed one above it.
s/AND \(wcat\.id = cat\.id OR \{\$under\}\)\)/AND wcat.id = cat.id)/;
