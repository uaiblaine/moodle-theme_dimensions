# A scheduled card with no route offers no way to its details on the landing page.
s/: \(\$this->values->model > 0 \? 'category_cta_details' : null\),/: null,/;
