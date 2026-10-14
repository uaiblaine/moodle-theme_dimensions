# Serve whatever reached the card-cover area without asking core whether it is an image: a
# same-origin .html written
# through the file API is then script for everyone who may see the category, and the hero area
# beside it still refuses the very same bytes.
s/if \(!\$cover->is_valid_image\(\)\)/if (false)/;
