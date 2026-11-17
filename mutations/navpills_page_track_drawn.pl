# Never draw the row after the header of a page without a hero: the band is gone and nothing replaces it.
s/return \$this->focus_full_header\(\) \. \$this->page_navpills\(\);/return \$this->focus_full_header();/;
