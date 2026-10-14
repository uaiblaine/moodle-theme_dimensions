# Keep core's URL for the course image on a public page: a visitor then gets the login
# page instead of the image, under forcelogin.
s/if \(\$this->ispublic\) \{(?=\n[ \t]*\$file = publicfiles::hero_file)/if (false) {/;
