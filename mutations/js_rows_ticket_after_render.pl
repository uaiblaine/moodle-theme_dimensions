# Check "the latest request wins" only before the rows render. An answer overtaken while its cards were rendering then
# paints the count line, rewrites the posters over the newer request's pending state and tells the posters' component,
# which can open an area by itself that the newer answer would not. The sweep exercises listing_account_source_test.
s/(const items = await Listing\.renderCards\(response\.cards \|\| \[\]\);\n)    if \(ticket !== seq\) \{\n        return;\n    \}\n/$1/;
