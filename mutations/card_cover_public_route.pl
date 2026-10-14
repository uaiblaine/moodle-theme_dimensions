# Mint core's course/overviewfiles URL for the public shell too: the cover of every card on the
# public category page is then a URL that answers a visitor with the login page while the site
# forces login, and the page looks right to everybody who is logged in.
s/        if \(\$public\) \{/        if (false) {/;
