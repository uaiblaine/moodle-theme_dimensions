# Serve the hero image of whatever category the URL names to a logged-in requester: a category core hides from
# them, or one the plugin withholds, then hands its image to a URL minted while they could still see it.
s/\n[ \t]*: self::viewer_may_see\(\$categoryid\);/\n            : true;/;
