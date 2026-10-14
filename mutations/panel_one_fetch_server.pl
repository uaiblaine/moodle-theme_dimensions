# Resolve every poster's panel on the server before drawing the open one - the cheapest shape of
# "prefetch the panels". The page's cost then grows with the number of posters, one whole listing
# each, on the page that is the site's front door.
s/(\$result->panel = self::resolve\(self::panel_criteria\()/foreach (\$candidates as \$each) {\n                    self::resolve(self::panel_criteria(\$criteria, (int) \$each['id'], !empty(\$each['istext'])));\n                }\n                $1/;
