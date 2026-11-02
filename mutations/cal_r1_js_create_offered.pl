# Create on a double-click whatever core says of "New event".
s/\(edits\?\.create \? create : undefined\);/(true ? create : undefined);/;
