# The entry acts only on a POST: a GET with the field and a valid key is the plain page.
s/        \$enter = \(\$_SERVER\['REQUEST_METHOD'\] \?\? ''\) === 'POST'\n            && /        \$enter = /;
