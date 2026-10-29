# Keep a sheet a reset may have purged before it was written: the old sheet is served under the new revision.
s/if \(\$after !== \[\$rev, \$subrev\]\)/if (false)/;
