# Hand the visitor's call a cachekey: a GET answered with ninety days of public caching.
s/\}, \{loginrequired: false\}\);/}, {loginrequired: false, cachekey: Config.langrev});/;
