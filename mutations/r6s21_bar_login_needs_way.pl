# Give every visitor the login bar, whatever showlogin says.
s/\} else if \(\$exported->showlogin\) \{/} else if (\$exported->isvisitor) {/
