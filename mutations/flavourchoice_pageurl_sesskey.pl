# The menu links carry the page's session key in their return address.
s/(\$back = new \\moodle_url\(\$returnurl\);)\s+\$back->remove_params\('sesskey'\);/$1/;
