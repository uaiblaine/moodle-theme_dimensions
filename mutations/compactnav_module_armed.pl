# Never arm the module: the armed page waits for a decision nobody makes, until the fail-open lets it go.
s/\$this->page->requires->js_call_amd\('theme_dimensions\/compactnav', 'init', \[compactnav::client_config\(\)\]\);//;
