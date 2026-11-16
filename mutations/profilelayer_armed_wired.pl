# Print the layer without arming its module: the user button stays the dropdown's and nothing opens the layer.
s/\n\s+\$this->page->requires->js_call_amd\('theme_dimensions\/profilelayer', 'init'\);\n/\n/;
