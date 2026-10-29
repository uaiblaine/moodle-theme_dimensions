# A cached answer of Boost Union's own is never replaced: the choice loses to the category rule.
s/!is_object\(\$cached\) \|\| \(int\) \$cached->id !== \(int\) \$entry->record->id/!is_object(\$cached)/;
