# Treat every sheet as missing: each run of the task recompiles sheets that are already built.
s/if \(!file_exists\(builder::sheet_path\(\$rev, \$subrev, \$flavourid, \$direction\)\)\)/if (true)/;
