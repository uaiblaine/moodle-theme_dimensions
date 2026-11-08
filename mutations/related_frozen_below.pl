# A course under a frozen category would be suggested: only its own context's flag would be read.
s/AND \(lck\.id = ctx\.id OR \{\$below\}\)\)/AND lck.id = ctx.id)/;
