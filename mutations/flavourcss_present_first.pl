# Skip the check before the lock: every run of the task queues on the theme's lock for sheets already built.
s/if \(file_exists\(self::sheet_path\(\$rev, \$subrev, \$flavourid, \$direction\)\)\)/if (false)/;
