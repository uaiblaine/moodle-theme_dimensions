# Drop the data: URI skip: the drawn cover, which nothing fetches, takes the high fetch priority.
s/ && !str_starts_with\(\(string\) \$card->imageurl, .data:.\)//;
