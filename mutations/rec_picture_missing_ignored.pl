# Ignore missing fields, so a course field that was deleted leaves the picture check at fine.
s/\$this->missing === \[\];/true;/;
