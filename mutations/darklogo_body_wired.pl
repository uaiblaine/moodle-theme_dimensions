# Unwire the renderer: body_attributes() stops asking for the dark logo classes, so no page arms the rules.
s/darklogo::body_classes\(\$this->page->theme, \$this->darklogo_flavour\(\)\)/[]/;
