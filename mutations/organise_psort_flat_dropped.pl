# Keep the grouped order under a flat organisation, where it orders nothing and travels into every address.
s/\$this->psort = \$this->is_grouped\(\) \? \$psort : '';/\$this->psort = \$psort;/;
