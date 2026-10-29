# Store a sheet whose revision moved during the compile: it outlives the reset under a revision-less MUC key.
s/if \(self::revisions\(\) !== \[\$rev, \$subrev\]\)/if (false)/;
