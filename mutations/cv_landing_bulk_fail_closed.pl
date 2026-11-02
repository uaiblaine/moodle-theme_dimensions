# Ask a course predicate class that is not there.
s/        if \(!\$ids \|\| !class_exists\(\$predicate\)\) \{/        if (!\$ids) {/;
