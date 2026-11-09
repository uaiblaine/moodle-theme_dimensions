# The form would forget the stored choice, and a save would empty it.
s/            \$mform->setDefault\(choice::ELEMENT, choice::chosen\(choice::decode\(\$stored\), \$linked\)\);\n//;
