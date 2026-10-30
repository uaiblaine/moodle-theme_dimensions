# The choice page writes the look its address names, on a GET.
s/\$data = \$form->get_data\(\);/$data = (object) ['flavour' => optional_param('flavour', 0, PARAM_INT)];/;
