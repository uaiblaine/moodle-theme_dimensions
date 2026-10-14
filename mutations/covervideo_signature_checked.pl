# Trust a cover video by its NAME on the route: serve whatever sits in the area under the hashed
# address, whatever its first bytes say. Core derives a stored file's mimetype from the extension
# and has no video validator, so a text file, a PDF or an HTML document called capa.mp4 is then
# handed to a browser as a video from the site's own origin.
s/if \(!self::is_valid_video\(\$video\)\)/if (false)/;
