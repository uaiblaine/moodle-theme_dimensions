# Compile under the lock even when the shared entry holds the sheet: a new cron machine recompiles every flavour.
s/\$restored = \$this->restore_at\(\$rev, \$subrev, \$flavourid, \$direction\);/\$restored = null;/;
