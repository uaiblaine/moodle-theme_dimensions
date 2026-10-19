# Never append the actions to the navbar: the visitor is left with no way to log in from the bar.
s/\$output \.= \$this->render_from_template\('theme_dimensions\/navbar_guest', guestnav::context\(\)\);/\$output .= '';/;
