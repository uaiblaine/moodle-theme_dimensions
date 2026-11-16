# Place a key by the address with its query string, which carries the category id.
s/\$url->out_omit_querystring\(\)/\$url->out(false)/;
