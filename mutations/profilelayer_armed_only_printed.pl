# Arm the module before the guard: a visitor, the guest and a site with the layer off get it armed too.
s/\n\s+\$this->page->requires->js_call_amd\('theme_dimensions\/profilelayer', 'init'\);\n/\n/;
s/(\n(\s+)\$layer = \$this->profilelayer;\n)/$1$2\$this->page->requires->js_call_amd('theme_dimensions\/profilelayer', 'init');\n/;
