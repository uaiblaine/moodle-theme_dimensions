# Give a hybrid course's recurring meetings a street address row.
s/\$withaddress = !\$this->is_hybrid\(\) \|\| \$this->is_event\(\);/\$withaddress = true;/;
