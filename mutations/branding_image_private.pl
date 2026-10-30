# Send the branding image public for a visitor: a shared proxy then keeps serving a category's image for the
# whole hashed lifetime after the category stops being public, since the URL carries only the content hash.
s/\$options = \['cacheability' => 'private'\];/\$options = ['cacheability' => !isloggedin() ? 'public' : 'private'];/;
