# Always emit the image tags: a category with no image anywhere then ships an empty og:image
# claiming to be 1200x630.
s/branding::ogimage\(\$categoryid\)/branding::ogimage(\$categoryid) ?? ['url' => '', 'type' => 'image\/jpeg', 'width' => 1200, 'height' => 630]/;
