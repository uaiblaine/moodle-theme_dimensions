# Keep a key the vocabulary does not hold.
s/array_values\(array_intersect\(keys::ALL, array_filter\(\$off, 'is_string'\)\)\)/array_values(array_filter(\$off, 'is_string'))/;
