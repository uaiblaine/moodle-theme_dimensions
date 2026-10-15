# Send "Enter with key" to the card's own link, which forwards a course with a model to the landing page.
s/\n\s*'category_cta_key' => redirector::standard_page\(\(int\) \$this->course->id\),//;
