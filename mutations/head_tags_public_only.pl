# Put the head tags on every landing page, public or not.
s/        if \(\$ispublic\) \{\n            opengraph::set/        if (true) {\n            opengraph::set/;
