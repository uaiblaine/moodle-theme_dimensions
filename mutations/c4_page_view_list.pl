# Hand a list for the view or the week to optional_param(), which throws.
s/return is_scalar\(\$raw\) \? \(string\) \$raw : '';/return (string) optional_param(\$name, '', PARAM_RAW);/;
