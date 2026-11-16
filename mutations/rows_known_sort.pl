# Answer a sort outside its vocabulary instead of refusing it, in the one check both listing services ask.
s/        if \(!in_array\(\$sort, \$vocabulary, true\)\) \{/        if (false) {/;
