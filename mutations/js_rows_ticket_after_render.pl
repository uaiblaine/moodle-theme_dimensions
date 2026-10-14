# Check "the latest request wins" only before the rows render. An answer overtaken while its cards were
# rendering then paints the count line, rewrites the posters over the newer request's pending state and
# tells the panel's module, which can open an area by itself that the newer answer would not - and
# nothing ever closes a panel on its own. The sweep exercises amd_source_test's assertion.
s/(const items = await renderCards\(response\.cards \|\| \[\]\);\n)    if \(ticket !== seq\) \{\n        return;\n    \}\n/$1/;
