# Keep the stored layer once printed: a second call after the main region prints it a second time.
s/\n[^\n]*\$this->profilelayer = null;\n/\n/;
