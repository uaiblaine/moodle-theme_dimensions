# Skip the cleaned re-read of the public page's textarea sections: trusted editor HTML then
# reaches every visitor uncleaned.
s/        if \(\$this->ispublic\) \{\n            \/\* The textarea\x27s export_value\(\).*?\n        \}\n//s;
