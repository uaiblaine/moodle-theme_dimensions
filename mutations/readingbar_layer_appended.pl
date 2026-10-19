# Never append the context layer to the navbar: there is nothing for the module to swap to.
s/\$output \.= \$this->render_from_template\('theme_dimensions\/navbar_readingbar', readingbar::context\(\$this->page\)\);/\$output .= '';/;
