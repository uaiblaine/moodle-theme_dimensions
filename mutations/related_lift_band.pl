# The page would draw its automatic band whatever the texts' shortcode asks for.
s/\$found = \$lifted === null \? band::courses\(\$this->course, \$this->values, \$trail\) : \$lifted\['found'\];/\$found = band::courses(\$this->course, \$this->values, \$trail);/;
