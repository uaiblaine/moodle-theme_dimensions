# Merge the halves without sorting by start.
s/^        usort\(\$items, static fn\(array \$a, array \$b\): int => \[\$a\['timestart'\], \$a\['source'\], \$a\['id'\]\]\n/        if (false) usort(\$items, static fn(array \$a, array \$b): int => [\$a['timestart'], \$a['source'], \$a['id']]\n/m;
