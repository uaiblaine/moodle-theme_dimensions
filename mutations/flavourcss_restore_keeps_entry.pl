# Delete the MUC entry on a stale restore: a visitor's compile at the new revisions is thrown away.
s/self::forget\(\$rev, \$subrev, \$flavourid, \$direction, false\);/self::forget(\$rev, \$subrev, \$flavourid, \$direction);/;
