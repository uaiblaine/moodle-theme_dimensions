# Memoise the cards' labels across languages: a request that switches language reads the first one's labels.
s/self::\$strings\[current_language\(\)\]/self::\$strings['any']/;
