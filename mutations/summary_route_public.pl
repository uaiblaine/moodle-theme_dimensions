# Keep core's URLs for the summary's files on a public page: every image embedded in the
# summary then redirects a visitor to the login page.
s/if \(\$this->ispublic\) \{(?=\n[ \t]*\/\/ Embedded files go through)/if (false) {/;
