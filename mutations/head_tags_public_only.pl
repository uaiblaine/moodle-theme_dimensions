# Put the head tags on every hotsite, public or not.
s/        if \(\$ispublic\) \{\n            opengraph::set/        if (true) {\n            opengraph::set/;
