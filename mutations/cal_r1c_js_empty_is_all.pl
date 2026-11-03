# An empty list draws every box off again, while the server reads it as every option.
s/kept\.length === 0 \|\| kept\.includes\(value\);/kept.includes(value);/;
