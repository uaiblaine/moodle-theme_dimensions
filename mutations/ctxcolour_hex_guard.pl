# Let any stored value into the SCSS: a colour name, an rgb() or a forced value no validation saw.
s/if \(!preg_match\('\/\^#\?\(\?:\[0-9a-f\]\{3\}\)\{1,2\}\$\/i', \$stored\)\) \{/if (\$stored === '') {/;
