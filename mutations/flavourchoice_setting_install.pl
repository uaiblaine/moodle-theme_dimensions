# A fresh install leaves the looks a person may choose unstored: upgradesettings.php loops on an empty page.
s/\n\s*\\theme_dimensions\\local\\flavourchoice::ensure_setting\(\);//;
