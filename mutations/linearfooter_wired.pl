# Never arm the footer's module: the reader reaches the end of the activity with "Next" hidden.
s/if \(\\core_courseformat\\local\\linearnavigationsettings::show_navigation_footer\(\$this->page\)\) \{/if (false) {/;
