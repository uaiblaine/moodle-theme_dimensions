# Sort a menu by bytes (strcmp) instead of the viewer's language (core_collator).
s/\\core_collator::asort_array_of_arrays_by_key\(\$items, 'text'\);/usort(\$items, fn(\$a, \$b) => strcmp(\$a['text'], \$b['text']));/;
