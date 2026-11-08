# A frozen course would be suggested while context freezing is on, though nobody can enrol in it.
s/WHERE lck\.locked = 1 AND \(lck\.id = ctx\.id OR \{\$below\}\)\)/WHERE 1 = 0)/;
