# The props carry any zoom they are handed.
s/\$this->zoom = in_array\(\$zoom, \[page::ZOOM_WEEK, page::ZOOM_MONTH\], true\) \? \$zoom : '';/\$this->zoom = \$zoom;/;
