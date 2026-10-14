# Skip the cleaner on the public page's textarea sections: trusted editor HTML then
# reaches every visitor uncleaned.
s/\$html = \$this->ispublic \? clean_text\(\$html, FORMAT_HTML\) : \$html;/\$html = \$html;/;
