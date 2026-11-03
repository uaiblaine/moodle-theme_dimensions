# Run every text filter over a visitor's names, not core's multilang filter alone.
s/        return format_string\(\$text, true, \['context' => \$context, 'escape' => false, 'filter' => false\]\);/        return format_string(\$text, true, ['context' => \$context, 'escape' => false, 'filter' => true]);/;
