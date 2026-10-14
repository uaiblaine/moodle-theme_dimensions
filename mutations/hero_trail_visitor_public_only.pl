# Name every ancestor to the internet: a visitor's trail then prints a listed category's name and
# links to a page that answers with the login form.
s/if \(!publicaccess::is_public\(\$parentid\)\) \{/if (false) {/;
