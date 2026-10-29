# Skip the check under the lock: a sheet a visitor built while the builder waited is compiled again.
s/if \(file_exists\(\$sheet\)\)/if (false)/;
