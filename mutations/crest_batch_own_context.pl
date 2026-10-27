# Keep a badge file of another course's context that happens to carry the row's item id: the card shows a file
# that is not its course's badge.
s/ \|\| \(int\) \$record->contextid !== \$contexts\[\$dataid\]//;
