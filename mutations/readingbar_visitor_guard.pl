# Lay the layer over the visitor's "Log in" and "Create account".
s/if \(guestnav::applies\(\$page\)\) \{\n(\s*)return false;/if (false) {\n$1return false;/;
