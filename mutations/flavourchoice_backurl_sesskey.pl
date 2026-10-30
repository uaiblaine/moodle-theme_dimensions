# The choice page sends the person back to an address that still carries its session key.
s/(\$back = new \\moodle_url\(\$local\);)\s+\$back->remove_params\('sesskey'\);/$1/;
