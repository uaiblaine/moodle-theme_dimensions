# Leave the open panel out of the page's own parameters: folding the hero (A1), paging the direct
# courses or picking a quick date then reloads the page with the panel the reader was reading shut.
s/\n[ \t]*\$params \+= \$this->panel_params\(\);//;
