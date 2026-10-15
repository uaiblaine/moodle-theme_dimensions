# A form with no key is the plain landing page, never an error page.
s/            && \$sesskey !== ''\n//;
