# A form with no key is the plain hotsite, never an error page.
s/            && \$sesskey !== ''\n//;
