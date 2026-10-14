# Serve the hero image of whatever category a VISITOR names: a category that is not public then hands its
# image to the internet through a URL minted while it was, or guessed.
s/\? \(publicaccess::is_public\(\$categoryid\) \|\| self::viewer_may_see\(\$categoryid\)\)/? true/;
