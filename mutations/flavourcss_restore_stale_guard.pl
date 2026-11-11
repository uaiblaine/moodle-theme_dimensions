# Keep the files a restore wrote while a reset purged: the old sheet is served under the new revision.
s/if \(\(\$this->revisions\)\(\) !== \[\$rev, \$subrev\]\)/if (false)/;
