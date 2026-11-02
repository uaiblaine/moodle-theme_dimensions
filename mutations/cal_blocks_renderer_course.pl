# The renderer reads the site in place of the page's course.
s/\(int\) \$this->page->course->id, coreview::account\(\)/(int) SITEID, coreview::account()/;
