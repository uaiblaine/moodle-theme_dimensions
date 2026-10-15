# Draw the theme's own previous and next row where core 5.3 already prints its linear navigation
# footer: two navigations under the text of every topics and weeks activity in focus mode.
s/\n[^\n]*if \(\\core_courseformat\\local\\linearnavigationsettings::show_navigation_footer\(\$this->page\)\) \{\n[^\n]*return '';\n[^\n]*\}\n//;
