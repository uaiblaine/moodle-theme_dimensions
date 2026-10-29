# The setting is handed an array callable again, which core's multicheckbox keeps as its choices.
s/fn\(\): array => \\theme_dimensions\\local\\flavourchoice::choices\(\)/[\\theme_dimensions\\local\\flavourchoice::class, 'choices']/;
