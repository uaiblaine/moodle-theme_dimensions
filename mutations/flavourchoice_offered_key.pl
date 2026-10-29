# The cached list ignores the offered ids: a setting change keeps serving the old list.
s/\$key = current_language\(\) \. '_' \. implode\('_', \$ids\);/\$key = current_language();/;
