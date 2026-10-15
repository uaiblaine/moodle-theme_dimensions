# Announce the file name as it is stored, extension and slug included: a screen reader then reads
# "crest-alpha.png" where the institution's name was the whole point of naming the file.
s/(public static function badge_alt\(string \$filename\): string \{)/$1\n        return \$filename;/;
