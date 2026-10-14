# Draw the second factor on the loginsteps shell whether or not that shell is the site's sign-in door.
s~return class_exists\(\$class\) && method_exists\(\$class, 'available'\) && \$class::available\(\);~return class_exists(\$class);~;
