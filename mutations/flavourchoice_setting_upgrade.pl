# A site installed with no flavour keeps the setting unstored after the upgrade: the empty page stays.
s/\n\s*\\theme_dimensions\\local\\flavourchoice::ensure_setting\(\);//;
