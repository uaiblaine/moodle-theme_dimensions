# Rename the standard_top_of_body_html() override so core never calls it. The shell is still read and the
# foot still printed; only the bar and the status line never reach the page.
s/function standard_top_of_body_html\(/function standard_top_of_body_html_disabled(/;
