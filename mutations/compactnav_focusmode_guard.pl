# Forget focus mode: the mode is armed on a page whose bar is focus mode's own.
s/if \(focusmode::applies\(\$page\)\) \{\n(\s*)return false;/if (false) {\n$1return false;/;
