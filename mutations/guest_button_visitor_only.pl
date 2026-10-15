# The landing page's guest button leads to the entry for a visitor who is not logged in only.
s/        if \(!isloggedin\(\) && guestaccess::available\(\$this->course\)\) \{/        if (guestaccess::available(\$this->course)) {/;
