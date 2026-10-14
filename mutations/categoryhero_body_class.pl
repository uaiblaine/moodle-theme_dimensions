# Stop adding the body class the editor's whole stylesheet is scoped under. Every rule of
# scss/categoryhero.scss - the swatch row, the quick colours, the contrast panel and its tones -
# then matches nothing, while the page still renders, the module still runs and the Behat
# scenario still finds every string it asserts.
s/^\$PAGE->add_body_class\('dim-categoryhero'\);\n//m;
